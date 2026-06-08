<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class TelephonyDiagnosticsController extends Controller
{
    /**
     * Run all telephony readiness checks and return a structured JSON result.
     * Each check has: label, status (ok|warn|fail), message.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [];

        // 1. ViciDial Agent API reachability
        $checks[] = $this->checkAgentApi();

        // 2. ViciDial Non-Agent API reachability
        $checks[] = $this->checkNonAgentApi();

        // 3. AMI TCP connectivity
        $checks[] = $this->checkAmi();

        // 4. WebRTC WSS endpoint reachability
        $checks[] = $this->checkWebRtc();

        // 5. Campaign → server mapping completeness
        $checks[] = $this->checkCampaignServerMappings();

        // 6. Agent user credential completeness
        $checks[] = $this->checkAgentCredentials();

        // 7. Media path (SIP.js vs ViciPhone) configuration
        $checks[] = $this->checkMediaPath();

        // 8. Go-live readiness checks
        $checks[] = $this->checkDatabaseConnection();
        $checks[] = $this->checkMigrationStatus();
        $checks[] = $this->checkRequiredTables();
        $checks[] = $this->checkQueueFailures();
        $checks[] = $this->checkWebhookSecrets();
        $checks[] = $this->checkBroadcastingConfig();

        $overallOk = collect($checks)->every(fn ($c) => $c['status'] === 'ok');

        return response()->json([
            'ok' => $overallOk,
            'checks' => $checks,
        ]);
    }

    private function checkAgentApi(): array
    {
        $url = config('vicidial.api_url', '');
        if (empty($url)) {
            return ['label' => 'ViciDial Agent API', 'status' => 'fail', 'message' => 'VICI_API_URL is not set in .env'];
        }

        try {
            $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
                ->connectTimeout(4)->timeout(6)->get($url);
            // ViciDial returns a plain text error when no credentials are sent – that's fine,
            // it still means the server is reachable.
            $reachable = $response->status() < 500;

            return [
                'label' => 'ViciDial Agent API',
                'status' => $reachable ? 'ok' : 'warn',
                'message' => $reachable
                    ? 'Reachable: HTTP '.$response->status().' ('.$url.')'
                    : 'Responded with HTTP '.$response->status().' – server may be misconfigured.',
            ];
        } catch (\Throwable $e) {
            return ['label' => 'ViciDial Agent API', 'status' => 'fail', 'message' => 'Unreachable: '.$e->getMessage().' ('.$url.')'];
        }
    }

    private function checkNonAgentApi(): array
    {
        $url = config('vicidial.non_agent_api_url', '');
        if (empty($url)) {
            // Try deriving from Agent API URL
            $agentUrl = config('vicidial.api_url', '');
            if (! empty($agentUrl) && str_contains($agentUrl, 'agc/api.php')) {
                $url = preg_replace('#agc/api\.php.*$#', 'non_agent_api.php', $agentUrl) ?: '';
            }
        }

        if (empty($url)) {
            return ['label' => 'ViciDial Non-Agent API', 'status' => 'fail', 'message' => 'VICI_NON_AGENT_API_URL is not set and cannot be derived from VICI_API_URL'];
        }

        try {
            $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
                ->connectTimeout(4)->timeout(6)->get($url);
            $reachable = $response->status() < 500;

            return [
                'label' => 'ViciDial Non-Agent API',
                'status' => $reachable ? 'ok' : 'warn',
                'message' => $reachable
                    ? 'Reachable: HTTP '.$response->status().' ('.$url.')'
                    : 'Responded with HTTP '.$response->status().' – server may be misconfigured.',
            ];
        } catch (\Throwable $e) {
            return ['label' => 'ViciDial Non-Agent API', 'status' => 'fail', 'message' => 'Unreachable: '.$e->getMessage().' ('.$url.')'];
        }
    }

    private function checkAmi(): array
    {
        $host = config('asterisk.host', '');
        $port = (int) config('asterisk.port', 5038);
        $secret = config('asterisk.secret', '');

        if (empty($host) || empty($secret)) {
            return ['label' => 'Asterisk AMI', 'status' => 'warn', 'message' => 'AMI is not configured (ASTERISK_AMI_HOST or ASTERISK_AMI_SECRET missing). Browser calls will not work.'];
        }

        try {
            $sock = @fsockopen($host, $port, $errno, $errstr, 3);
            if (! $sock) {
                return ['label' => 'Asterisk AMI', 'status' => 'fail', 'message' => "Cannot connect to {$host}:{$port} – {$errstr} ({$errno})"];
            }
            $banner = fgets($sock, 128);
            fclose($sock);
            $isAmi = str_starts_with((string) $banner, 'Asterisk Call Manager');

            return [
                'label' => 'Asterisk AMI',
                'status' => $isAmi ? 'ok' : 'warn',
                'message' => $isAmi
                    ? "Connected to {$host}:{$port} – ".trim((string) $banner)
                    : "TCP open but unexpected banner on {$host}:{$port}: ".trim((string) $banner),
            ];
        } catch (\Throwable $e) {
            return ['label' => 'Asterisk AMI', 'status' => 'fail', 'message' => 'Exception: '.$e->getMessage()];
        }
    }

    private function checkWebRtc(): array
    {
        $wsUrl = config('webrtc.asterisk_ws_url', '');
        if (empty($wsUrl)) {
            return ['label' => 'WebRTC WSS', 'status' => 'warn', 'message' => 'ASTERISK_WS_URL not set – browser SIP calling will not work.'];
        }

        // Convert wss:// / ws:// to https:// / http:// for a basic TCP probe
        $httpUrl = preg_replace('#^wss?://#', 'https://', $wsUrl) ?? $wsUrl;
        $httpUrl = preg_replace('#/ws$#', '', $httpUrl);

        try {
            // WebRTC probes always skip SSL verification since Asterisk typically uses self-signed certs.
            $response = Http::withoutVerifying()->connectTimeout(4)->timeout(6)->get($httpUrl);

            return [
                'label' => 'WebRTC WSS',
                'status' => 'ok',
                'message' => "HTTP endpoint at {$httpUrl} responded with ".$response->status().' (WSS upgrade not tested here).',
            ];
        } catch (\Throwable $e) {
            return ['label' => 'WebRTC WSS', 'status' => 'fail', 'message' => "Cannot reach {$httpUrl}: ".$e->getMessage()];
        }
    }

    private function checkCampaignServerMappings(): array
    {
        $campaigns = Campaign::pluck('code')->all();
        if (empty($campaigns)) {
            return ['label' => 'Campaign → ViciDial Server Mapping', 'status' => 'warn', 'message' => 'No campaigns found.'];
        }

        // Eager-load active servers keyed by campaign_code so we avoid N+1 queries.
        $servers = VicidialServer::where('is_active', true)
            ->whereIn('campaign_code', $campaigns)
            ->get(['campaign_code', 'api_user', 'api_pass'])
            ->keyBy('campaign_code');

        $missing = [];
        $noApiUser = [];
        foreach ($campaigns as $code) {
            $server = $servers->get($code);
            if (! $server) {
                $missing[] = $code;
            } elseif (empty($server->api_user) || empty($server->api_pass)) {
                $noApiUser[] = $code;
            }
        }

        $issues = [];
        if (! empty($missing)) {
            $issues[] = 'No active server for: '.implode(', ', $missing);
        }
        if (! empty($noApiUser)) {
            $issues[] = 'Missing Non-Agent API user/pass for: '.implode(', ', $noApiUser);
        }

        if (empty($issues)) {
            return ['label' => 'Campaign → ViciDial Server Mapping', 'status' => 'ok', 'message' => count($campaigns).' campaign(s) all have active server mappings with Non-Agent API credentials.'];
        }

        return ['label' => 'Campaign → ViciDial Server Mapping', 'status' => 'warn', 'message' => implode('; ', $issues)];
    }

    private function checkAgentCredentials(): array
    {
        $agents = User::whereIn('role', [User::ROLE_AGENT, User::ROLE_TEAM_LEADER])
            ->withTrashed(false)
            ->get(['id', 'username', 'vici_user', 'vici_pass', 'extension', 'sip_password']);

        if ($agents->isEmpty()) {
            return ['label' => 'Agent Credentials', 'status' => 'warn', 'message' => 'No agent/team-leader users found.'];
        }

        $missingVici = [];
        $missingSip = [];

        foreach ($agents as $u) {
            if (empty($u->vici_user) || empty($u->vici_pass)) {
                $missingVici[] = $u->username;
            }
            if (empty($u->extension) || empty($u->sip_password)) {
                $missingSip[] = $u->username;
            }
        }

        $issues = [];
        if (! empty($missingVici)) {
            $issues[] = 'Missing ViciDial credentials ('.count($missingVici).'): '.implode(', ', array_slice($missingVici, 0, 5)).(count($missingVici) > 5 ? '...' : '');
        }
        if (! empty($missingSip)) {
            $issues[] = 'Missing SIP/WebRTC credentials ('.count($missingSip).'): '.implode(', ', array_slice($missingSip, 0, 5)).(count($missingSip) > 5 ? '...' : '');
        }

        if (empty($issues)) {
            return ['label' => 'Agent Credentials', 'status' => 'ok', 'message' => $agents->count().' agent(s) all have ViciDial and SIP credentials configured.'];
        }

        return ['label' => 'Agent Credentials', 'status' => 'warn', 'message' => implode('; ', $issues)];
    }

    /**
     * Surfaces the telephony media_path setting so admins see which WebRTC
     * stack (SIP.js vs ViciPhone) is active. Flags `both` as a migration
     * warning because double-registering the same extension can break calls.
     */
    private function checkMediaPath(): array
    {
        $mediaPath = (string) config('webrtc.media_path', 'sipjs');
        $allowed = ['sipjs', 'viciphone', 'both'];

        if (! in_array($mediaPath, $allowed, true)) {
            return [
                'label' => 'Telephony Media Path',
                'status' => 'fail',
                'message' => "Invalid TELEPHONY_MEDIA_PATH '{$mediaPath}'. Expected one of: ".implode(', ', $allowed),
            ];
        }

        if ($mediaPath === 'both') {
            return [
                'label' => 'Telephony Media Path',
                'status' => 'warn',
                'message' => 'media_path=both: CRM SIP.js and Vicidial ViciPhone are BOTH active. Use only while migrating; double-registering the same extension can cause call setup failures.',
            ];
        }

        $description = $mediaPath === 'sipjs'
            ? 'CRM SIP.js owns WebRTC audio; Vicidial iframe is UI only.'
            : 'Vicidial ViciPhone owns WebRTC audio; SIP.js is disabled.';

        return [
            'label' => 'Telephony Media Path',
            'status' => 'ok',
            'message' => "media_path={$mediaPath}: {$description}",
        ];
    }

    private function checkDatabaseConnection(): array
    {
        try {
            DB::connection()->getPdo();

            return ['label' => 'Database Connection', 'status' => 'ok', 'message' => 'Application database connection is available.'];
        } catch (\Throwable $e) {
            return ['label' => 'Database Connection', 'status' => 'fail', 'message' => 'Database connection failed: '.$e->getMessage()];
        }
    }

    private function checkMigrationStatus(): array
    {
        if (! Schema::hasTable('migrations')) {
            return ['label' => 'Migration Status', 'status' => 'fail', 'message' => 'The migrations table is missing.'];
        }

        $files = collect(glob(database_path('migrations/*.php')) ?: [])
            ->map(fn (string $path): string => basename($path, '.php'));
        $ran = DB::table('migrations')->pluck('migration');
        $pending = $files->diff($ran)->values();

        if ($pending->isEmpty()) {
            return ['label' => 'Migration Status', 'status' => 'ok', 'message' => $files->count().' migration file(s) are applied.'];
        }

        return [
            'label' => 'Migration Status',
            'status' => 'fail',
            'message' => $pending->count().' pending migration(s): '.$pending->take(5)->implode(', ').($pending->count() > 5 ? '...' : ''),
        ];
    }

    private function checkRequiredTables(): array
    {
        $required = [
            'users',
            'campaigns',
            'forms',
            'form_fields',
            'call_sessions',
            'campaign_disposition_records',
            'agent_capture_records',
            'lead_hopper',
            'lead_imports',
            'vicidial_agent_sessions',
            'telephony_alerts',
            'personal_access_tokens',
            'user_widget_layouts',
        ];

        $missing = array_values(array_filter($required, fn (string $table): bool => ! Schema::hasTable($table)));
        if ($missing === []) {
            return ['label' => 'Required Tables', 'status' => 'ok', 'message' => count($required).' required table(s) are present.'];
        }

        return ['label' => 'Required Tables', 'status' => 'fail', 'message' => 'Missing table(s): '.implode(', ', $missing)];
    }

    private function checkQueueFailures(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['label' => 'Queue Failed Jobs', 'status' => 'warn', 'message' => 'failed_jobs table is missing.'];
        }

        $count = (int) DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
        if ($count === 0) {
            return ['label' => 'Queue Failed Jobs', 'status' => 'ok', 'message' => 'No failed jobs in the last 24 hours.'];
        }

        return ['label' => 'Queue Failed Jobs', 'status' => $count > 10 ? 'fail' : 'warn', 'message' => "{$count} failed job(s) in the last 24 hours."];
    }

    private function checkWebhookSecrets(): array
    {
        $missing = [];
        if ((string) config('asterisk.webhook_secret', '') === '') {
            $missing[] = 'ASTERISK_AMI_WEBHOOK_SECRET';
        }
        if ((string) config('vicidial.events_webhook_secret', '') === '') {
            $missing[] = 'VICIDIAL_EVENTS_WEBHOOK_SECRET';
        }

        if ($missing === []) {
            return ['label' => 'Webhook Secrets', 'status' => 'ok', 'message' => 'AMI and ViciDial webhook shared secrets are configured.'];
        }

        $status = app()->environment('production') ? 'fail' : 'warn';

        return ['label' => 'Webhook Secrets', 'status' => $status, 'message' => 'Missing shared secret(s): '.implode(', ', $missing)];
    }

    private function checkBroadcastingConfig(): array
    {
        $connection = (string) config('broadcasting.default', 'null');
        if ($connection === 'reverb') {
            $missing = [];
            foreach (['REVERB_APP_ID' => config('reverb.apps.apps.0.app_id'), 'REVERB_APP_KEY' => config('reverb.apps.apps.0.key'), 'REVERB_APP_SECRET' => config('reverb.apps.apps.0.secret')] as $name => $value) {
                if ((string) $value === '') {
                    $missing[] = $name;
                }
            }

            if ($missing === []) {
                return ['label' => 'Broadcasting Config', 'status' => 'ok', 'message' => 'Broadcasting uses Reverb and required app credentials are configured.'];
            }

            return ['label' => 'Broadcasting Config', 'status' => 'fail', 'message' => 'Broadcasting uses Reverb but is missing: '.implode(', ', $missing)];
        }

        return [
            'label' => 'Broadcasting Config',
            'status' => $connection === 'null' ? 'warn' : 'ok',
            'message' => "Broadcasting connection is '{$connection}'. Real-time UI requires Reverb for production.",
        ];
    }
}

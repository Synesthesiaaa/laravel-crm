<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Models\VicidialServer;
use Illuminate\Support\Facades\Http;

/**
 * Provisions / updates ViciDial user and phone records whenever CRM agent
 * credentials are saved, so both systems stay in sync without manual work.
 *
 * Uses the Non-Agent API (add_user/update_user and add_phone/update_phone)
 * on every active VicidialServer that has valid api_user / api_pass credentials.
 */
class VicidialCredentialSyncService
{
    public function __construct(
        protected TelephonyLogger $telephonyLogger
    ) {}

    /**
     * Push user + phone credentials for a newly-created CRM user to all
     * configured ViciDial servers that have API credentials.
     */
    public function syncOnCreate(User $user): void
    {
        if (empty($user->vici_user) || empty($user->vici_pass)) {
            return;
        }

        foreach ($this->activeServers() as $server) {
            $this->provisionUser($user, $server, creating: true);
            if (! empty($user->extension)) {
                $this->provisionPhone($user, $server, creating: true);
            }
        }
    }

    /**
     * Push updated user + phone credentials for an existing CRM user to all
     * configured ViciDial servers that have API credentials.
     */
    public function syncOnUpdate(User $user): void
    {
        if (empty($user->vici_user) || empty($user->vici_pass)) {
            return;
        }

        foreach ($this->activeServers() as $server) {
            // Try update first; if the user doesn't exist yet, create it.
            $updated = $this->provisionUser($user, $server, creating: false);
            if (! $updated) {
                $this->provisionUser($user, $server, creating: true);
            }

            if (! empty($user->extension)) {
                $updatedPhone = $this->provisionPhone($user, $server, creating: false);
                if (! $updatedPhone) {
                    $this->provisionPhone($user, $server, creating: true);
                }
            }
        }
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    /** @return VicidialServer[] */
    private function activeServers(): array
    {
        return VicidialServer::where('is_active', true)
            ->whereNotNull('api_user')
            ->whereNotNull('api_pass')
            ->where('api_user', '!=', '')
            ->where('api_pass', '!=', '')
            ->get()
            ->all();
    }

    private function nonAgentApiUrl(VicidialServer $server): string
    {
        $configured = trim((string) config('vicidial.non_agent_api_url', ''));
        if ($configured !== '') {
            return $configured;
        }
        $agentUrl = trim((string) $server->api_url);
        if (str_contains($agentUrl, 'agc/api.php')) {
            return preg_replace('#agc/api\.php.*$#', 'non_agent_api.php', $agentUrl) ?: '';
        }
        return rtrim($agentUrl, '/') . '/non_agent_api.php';
    }

    private function call(VicidialServer $server, array $params): array
    {
        $url = $this->nonAgentApiUrl($server);
        if ($url === '') {
            return ['success' => false, 'body' => 'Non-Agent API URL not resolvable.'];
        }

        $query = array_merge([
            'user'   => $server->api_user,
            'pass'   => $server->api_pass,
            'source' => $server->source ?: config('vicidial.default_source', 'crm_tracker'),
        ], $params);

        try {
            $response = Http::when(! config('vicidial.verify_ssl', true), fn ($h) => $h->withoutVerifying())
                ->connectTimeout((int) config('vicidial.connect_timeout', 5))
                ->timeout((int) config('vicidial.timeout', 10))
                ->get($url, $query);

            $body = trim($response->body());
            $success = ! str_starts_with(strtolower($body), 'error:');
            return ['success' => $success, 'body' => $body];
        } catch (\Throwable $e) {
            return ['success' => false, 'body' => $e->getMessage()];
        }
    }

    /**
     * @return bool  true when the operation succeeded
     */
    private function provisionUser(User $user, VicidialServer $server, bool $creating): bool
    {
        $function = $creating ? 'add_user' : 'update_user';
        $params = [
            'function'        => $function,
            'agent_user'      => $user->vici_user,
            'agent_pass'      => $user->vici_pass,
            'agent_full_name' => $user->full_name ?: $user->username,
            'agent_user_level' => 1,
            'agent_user_group' => 'AGENTS',
        ];

        if ($creating) {
            // add_user requires these fields; supply sensible defaults
            $params['agent_user_level'] = 1;
            $params['agent_user_group'] = 'AGENTS';
        }

        $result = $this->call($server, $params);

        $context = [
            'function'    => $function,
            'vici_user'   => $user->vici_user,
            'server'      => $server->server_name,
            'response'    => $result['body'],
        ];

        if ($result['success']) {
            $this->telephonyLogger->info('VicidialCredentialSyncService', 'User provisioned', $context);
        } else {
            // "USER ALREADY EXISTS" on add is not a real failure
            if ($creating && str_contains(strtoupper($result['body']), 'ALREADY EXISTS')) {
                return true;
            }
            $this->telephonyLogger->warning('VicidialCredentialSyncService', 'User provision failed', $context);
        }

        return $result['success'];
    }

    /**
     * @return bool  true when the operation succeeded
     */
    private function provisionPhone(User $user, VicidialServer $server, bool $creating): bool
    {
        if (empty($user->extension)) {
            return false;
        }

        // Extract the Asterisk server IP from the ViciDial server record or fall back to env
        $serverIp = $this->resolveAsteriskServerIp($server);
        if ($serverIp === '') {
            $this->telephonyLogger->warning('VicidialCredentialSyncService', 'Cannot sync phone – no Asterisk server IP', [
                'server' => $server->server_name,
                'extension' => $user->extension,
            ]);
            return false;
        }

        $function = $creating ? 'add_phone' : 'update_phone';
        $params = [
            'function'              => $function,
            'extension'             => $user->extension,
            'server_ip'             => $serverIp,
            'protocol'              => 'SIP',
            'phone_full_name'       => ($user->full_name ?: $user->username) . ' SIP ' . $user->extension,
            'dialplan_number'       => $user->extension,
            'voicemail_id'          => $user->extension,
            'phone_login'           => $user->extension,
            'is_webphone'           => 'Y',
            'webphone_auto_answer'  => 'Y',
        ];

        if (! empty($user->sip_password)) {
            $params['registration_password'] = $user->sip_password;
            $params['phone_pass']            = $user->sip_password;
        }

        $result = $this->call($server, $params);

        $context = [
            'function'  => $function,
            'extension' => $user->extension,
            'server'    => $server->server_name,
            'response'  => $result['body'],
        ];

        if ($result['success']) {
            $this->telephonyLogger->info('VicidialCredentialSyncService', 'Phone provisioned', $context);
        } else {
            if ($creating && str_contains(strtoupper($result['body']), 'ALREADY EXISTS')) {
                return true;
            }
            $this->telephonyLogger->warning('VicidialCredentialSyncService', 'Phone provision failed', $context);
        }

        return $result['success'];
    }

    private function resolveAsteriskServerIp(VicidialServer $server): string
    {
        // Prefer the db_host (the Asterisk host), fall back to the env AMI host
        if (! empty($server->db_host)) {
            return $server->db_host;
        }
        return (string) config('asterisk.host', '');
    }
}

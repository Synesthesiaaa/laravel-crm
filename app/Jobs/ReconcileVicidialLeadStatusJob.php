<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\SystemSetting;
use App\Models\VicidialServer;
use App\Services\Telephony\ViciDialInboundDispoService;
use App\Support\VicidialDispositionMap;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PDO;

class ReconcileVicidialLeadStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SETTING_KEY = 'vicidial_dispo_poll_last_run_at';

    public function handle(ViciDialInboundDispoService $dispo): void
    {
        if (! config('vicidial.inbound_poll_enabled', false)) {
            return;
        }

        $setting = SystemSetting::query()->where('setting_key', self::SETTING_KEY)->first();
        $since = $setting && $setting->setting_value
            ? \Carbon\Carbon::parse($setting->setting_value)->subMinutes(2)
            : now()->subMinutes(15);

        foreach (VicidialServer::query()->where('is_active', true)->get() as $server) {
            if (trim((string) $server->db_host) === '' || trim((string) $server->db_username) === '') {
                continue;
            }

            try {
                $pdo = $this->connect($server);
                $sql = 'SELECT lead_id, vendor_lead_code, phone_number, status, modify_date FROM vicidial_list WHERE modify_date >= ? ORDER BY modify_date ASC LIMIT 500';
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$since->format('Y-m-d H:i:s')]);

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $lead = $this->resolveLeadFromVicidialRow($row);
                    if (! $lead) {
                        continue;
                    }
                    $viciStatus = (string) ($row['status'] ?? '');
                    $mapped = VicidialDispositionMap::mapVicidialToCrm($viciStatus);
                    if (strtoupper(trim((string) $lead->status)) === strtoupper($mapped)) {
                        continue;
                    }
                    $dispo->applyFromPoll($lead, $viciStatus, $row);
                }
            } catch (\Throwable $e) {
                Log::warning('ReconcileVicidialLeadStatusJob: poll failed for server', [
                    'server_id' => $server->id,
                    'campaign' => $server->campaign_code,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        SystemSetting::query()->updateOrInsert(
            ['setting_key' => self::SETTING_KEY],
            ['setting_value' => now()->toDateTimeString()]
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function resolveLeadFromVicidialRow(array $row): ?Lead
    {
        $vendor = isset($row['vendor_lead_code']) ? trim((string) $row['vendor_lead_code']) : '';
        if ($vendor !== '') {
            $byVendor = Lead::query()->where('vendor_lead_code', $vendor)->first();
            if ($byVendor) {
                return $byVendor;
            }
        }

        $phone = isset($row['phone_number']) ? trim((string) $row['phone_number']) : '';
        if ($phone !== '') {
            $byPhone = Lead::query()->where('phone_number', $phone)->first();
            if ($byPhone) {
                return $byPhone;
            }
        }

        if (! empty($row['lead_id']) && is_numeric($row['lead_id'])) {
            return Lead::query()->find((int) $row['lead_id']);
        }

        return null;
    }

    protected function connect(VicidialServer $server): PDO
    {
        $host = (string) $server->db_host;
        $port = (int) ($server->db_port ?: 3306);
        $db = (string) ($server->db_name ?: 'asterisk');
        $user = (string) $server->db_username;
        $pass = (string) ($server->db_password ?? '');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $db);

        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
}

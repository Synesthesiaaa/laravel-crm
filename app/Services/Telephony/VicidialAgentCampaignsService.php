<?php

namespace App\Services\Telephony;

use App\Models\User;
use App\Models\VicidialServer;
use App\Repositories\VicidialServerRepository;
use App\Support\OperationResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Resolves outbound/login campaigns allowed for a VICIdial agent user.
 *
 * Primary: Non-Agent API function `agent_campaigns` (requires API user level ≥ 8).
 * Fallback: read `vicidial_users.allowed_campaigns` via MySQL when server DB credentials exist.
 */
class VicidialAgentCampaignsService
{
    public function __construct(
        protected VicidialNonAgentApiService $nonAgentApi,
        protected VicidialServerRepository $serverRepository,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * @return OperationResult with data: campaigns: array<int, array{id: string, name: string}>, source: string, server_campaign_code?: string
     */
    public function getAllowedCampaignsForUser(User $user, ?string $contextCampaign = null): OperationResult
    {
        if (empty($user->vici_user)) {
            return OperationResult::failure('VICIdial user (vici_user) is not set for your account.');
        }

        $server = $this->resolveServer($contextCampaign);
        if (! $server) {
            return OperationResult::failure(
                'No active VICIdial server with Non-Agent API credentials. Configure api_user / api_pass on a vicidial_servers row.',
            );
        }

        if (! config('vicidial.agent_campaigns_lookup_enabled', true)) {
            return OperationResult::failure('VICIdial agent campaign lookup is disabled.');
        }

        $apiResult = $this->nonAgentApi->execute(
            $user,
            $server->campaign_code,
            'agent_campaigns',
            [
                'agent_user' => (string) $user->vici_user,
                'stage' => 'pipe',
                'header' => 'YES',
            ],
            true,
        );

        if ($apiResult->success) {
            $raw = (string) ($apiResult->data['raw_response'] ?? '');
            $ids = $this->parseAgentCampaignsResponse($raw);
            if ($ids !== []) {
                $campaigns = $this->buildCampaignList($server, $ids);

                return OperationResult::success([
                    'campaigns' => $campaigns,
                    'source' => 'non_agent_api',
                    'server_campaign_code' => $server->campaign_code,
                ]);
            }
        }

        $this->telephonyLogger->warning('VicidialAgentCampaignsService', 'agent_campaigns API did not return campaigns; trying DB', [
            'user_id' => $user->id,
            'vici_user' => $user->vici_user,
            'api_message' => $apiResult->message ?? null,
        ]);

        return $this->fetchCampaignsFromDatabase($user, $server);
    }

    protected function resolveServer(?string $contextCampaign): ?VicidialServer
    {
        if ($contextCampaign !== null && $contextCampaign !== '') {
            $s = $this->serverRepository->getForCampaign($contextCampaign);
            if ($s && $this->serverHasNonAgentCredentials($s)) {
                return $s;
            }
        }

        return $this->serverRepository->getFirstActiveWithNonAgentCredentials();
    }

    protected function serverHasNonAgentCredentials(VicidialServer $server): bool
    {
        $u = trim((string) ($server->api_user ?? ''));
        $p = trim((string) ($server->api_pass ?? ''));

        return $u !== '' && $p !== '';
    }

    /**
     * @return list<string>
     */
    public function parseAgentCampaignsResponse(string $raw): array
    {
        $lines = preg_split("/\r\n|\r|\n/", trim($raw)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with(strtolower($line), 'error:')) {
                continue;
            }
            if (str_starts_with(strtolower($line), 'notice:')) {
                continue;
            }
            if (! str_contains($line, '|')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 3) {
                continue;
            }
            if (strtolower($parts[0]) === 'user') {
                continue;
            }

            return $this->splitCampaignSegment($parts[1]);
        }

        return [];
    }

    /**
     * VICIdial returns allowed campaign IDs hyphen-separated (see NON-AGENT_API agent_campaigns).
     *
     * @return list<string>
     */
    protected function splitCampaignSegment(string $segment): array
    {
        $segment = trim($segment);
        if ($segment === '') {
            return [];
        }

        if (str_contains($segment, '-') && ! str_contains($segment, ' ')) {
            $ids = array_filter(array_map('trim', explode('-', $segment)));

            return $this->normalizeIds($ids);
        }

        $split = preg_split('/[\s,]+/', $segment) ?: [];

        return $this->normalizeIds($split);
    }

    /**
     * @param  array<int, string>  $ids
     * @return list<string>
     */
    protected function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            if (! preg_match('/^[0-9A-Za-z_-]{1,40}$/', $id)) {
                continue;
            }
            $out[] = $id;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $ids
     * @return list<array{id: string, name: string}>
     */
    protected function buildCampaignList(VicidialServer $server, array $ids): array
    {
        $names = $this->lookupCampaignNames($server, $ids);
        $list = [];
        foreach ($ids as $id) {
            $list[] = [
                'id' => $id,
                'name' => $names[$id] ?? $id,
            ];
        }

        return $list;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, string>
     */
    protected function lookupCampaignNames(VicidialServer $server, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $dbServer = $server;
        if (! $this->serverHasDatabaseCredentials($dbServer)) {
            $dbServer = $this->serverRepository->getFirstActiveWithDatabaseCredentials() ?? $dbServer;
        }
        if (! $this->serverHasDatabaseCredentials($dbServer)) {
            return [];
        }

        return $this->runOnVicidialDb($dbServer, function ($db) use ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->select(
                "SELECT campaign_id, campaign_name FROM vicidial_campaigns WHERE campaign_id IN ({$placeholders})",
                $ids,
            );
            $map = [];
            foreach ($rows as $row) {
                $cid = (string) ($row->campaign_id ?? '');
                if ($cid !== '') {
                    $map[$cid] = (string) ($row->campaign_name ?? $cid);
                }
            }

            return $map;
        }) ?? [];
    }

    protected function serverHasDatabaseCredentials(VicidialServer $server): bool
    {
        return trim((string) ($server->db_host ?? '')) !== ''
            && trim((string) ($server->db_username ?? '')) !== '';
    }

    /**
     * @template T
     *
     * @param  callable(\Illuminate\Database\Connection): T  $fn
     * @return (T is null ? null : T)
     */
    protected function runOnVicidialDb(VicidialServer $server, callable $fn): mixed
    {
        if (! $this->serverHasDatabaseCredentials($server)) {
            return null;
        }

        $name = 'vicidial_inline_'.$server->id;
        Config::set("database.connections.{$name}", [
            'driver' => 'mysql',
            'host' => $server->db_host,
            'port' => (int) ($server->db_port ?: 3306),
            'database' => $server->db_name !== '' ? $server->db_name : 'asterisk',
            'username' => $server->db_username,
            'password' => (string) ($server->db_password ?? ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]);

        try {
            return $fn(DB::connection($name));
        } catch (\Throwable $e) {
            $this->telephonyLogger->warning('VicidialAgentCampaignsService', 'VICIdial DB query failed', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            DB::purge($name);
        }
    }

    /**
     * Read allowed_campaigns from vicidial_users (space/comma separated in DB).
     */
    protected function fetchCampaignsFromDatabase(User $user, VicidialServer $apiServer): OperationResult
    {
        $dbServer = $apiServer;
        if (! $this->serverHasDatabaseCredentials($dbServer)) {
            $dbServer = $this->serverRepository->getFirstActiveWithDatabaseCredentials();
        }
        if (! $dbServer || ! $this->serverHasDatabaseCredentials($dbServer)) {
            return OperationResult::failure(
                'Could not load campaigns: Non-Agent API did not return data and no MySQL credentials are configured on vicidial_servers.',
            );
        }

        $ids = $this->runOnVicidialDb($dbServer, function ($db) use ($user) {
            $row = $db->table('vicidial_users')
                ->select('allowed_campaigns')
                ->where('user', $user->vici_user)
                ->first();

            if (! $row) {
                return [];
            }

            $raw = trim((string) ($row->allowed_campaigns ?? ''));

            return $this->splitCampaignSegment($raw);
        });

        if ($ids === null) {
            return OperationResult::failure('VICIdial database query failed.');
        }

        if ($ids === []) {
            return OperationResult::failure(
                'No allowed campaigns found for this agent in vicidial_users (and Non-Agent API did not return a list).',
            );
        }

        $campaigns = $this->buildCampaignList($dbServer, $ids);

        return OperationResult::success([
            'campaigns' => $campaigns,
            'source' => 'database',
            'server_campaign_code' => $apiServer->campaign_code,
        ]);
    }
}

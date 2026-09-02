<?php

namespace App\Services\Telephony;

use App\Models\Campaign;
use App\Models\VicidialServer;
use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class VicidialHistoricalCallProvider
{
    private const OUTBOUND_TABLE = 'vicidial_log';

    private const INBOUND_TABLE = 'vicidial_closer_log';

    /**
     * @param  array<int, string>  $campaignCodes
     * @param  array<string, mixed>  $filters
     */
    public function fetch(
        VicidialServer $server,
        Campaign $campaign,
        array $campaignCodes,
        array $filters = [],
        int $page = 1,
        int $perPage = 25,
    ): HistoricalCallProviderResult {
        $campaignCodes = array_values(array_filter(array_map(
            static fn (mixed $code): string => trim((string) $code),
            $campaignCodes,
        )));
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        if ($campaignCodes === []) {
            return HistoricalCallProviderResult::success([], 0, [
                'agents' => [],
                'statuses' => [],
                'campaigns' => [],
            ], ['source' => 'vicidial_database', 'server_id' => $server->getKey()]);
        }

        $startedAt = microtime(true);
        $connection = null;

        try {
            $connection = $this->makeConnection($server);
            $normalizedFilters = $this->normalizeFilters($filters);
            $recordQuery = $this->combinedQuery($connection, $campaignCodes, $normalizedFilters, true);
            $metadataQuery = $this->combinedQuery($connection, $campaignCodes, $normalizedFilters, false);
            $total = (int) (clone $recordQuery)->count();
            $rows = (clone $recordQuery)
                ->orderBy($this->sortColumn($normalizedFilters['sort']), $normalizedFilters['sort_direction'])
                ->orderByDesc('call_date')
                ->orderBy('source_table')
                ->orderBy('unique_call_id')
                ->forPage($page, $perPage)
                ->get();
            $metadata = (clone $metadataQuery)
                ->select(['vicidial_user', 'raw_status', 'vicidial_campaign_id'])
                ->distinct()
                ->get();
            $records = $rows->map(
                fn (object $row): HistoricalCallRecord => $this->normalizeRow($row, $campaign),
            )->values()->all();
            $filterOptions = [
                'agents' => $metadata->pluck('vicidial_user')->filter()->map(fn (mixed $value): string => (string) $value)->unique()->sort()->values()->all(),
                'statuses' => $metadata->pluck('raw_status')->filter()->map(fn (mixed $value): string => (string) $value)->unique()->sort()->values()->all(),
                'campaigns' => $metadata->pluck('vicidial_campaign_id')->filter()->map(fn (mixed $value): string => (string) $value)->unique()->sort()->values()->all(),
            ];
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->logger()->info('vicidial.call_history.fetch', 'Historical VICIdial call history fetched.', [
                'server_id' => $server->getKey(),
                'crm_campaign' => (string) $campaign->code,
                'mapped_campaign_count' => count($campaignCodes),
                'rows_received' => $total,
                'rows_returned' => count($records),
                'duration_ms' => $durationMs,
            ]);

            return HistoricalCallProviderResult::success($records, $total, $filterOptions, [
                'source' => 'vicidial_database',
                'server_id' => $server->getKey(),
                'rows_received' => $total,
                'rows_returned' => count($records),
                'duration_ms' => $durationMs,
            ]);
        } catch (Throwable $exception) {
            $this->logger()->error('vicidial.call_history.fetch', 'Historical VICIdial call history is unavailable.', [
                'server_id' => $server->getKey(),
                'crm_campaign' => (string) $campaign->code,
                'mapped_campaign_count' => count($campaignCodes),
                'error_class' => $exception::class,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return HistoricalCallProviderResult::failure(
                'VICIdial call history is currently unavailable. Please try again.',
                [
                    'classification' => 'REMOTE_DATABASE_ERROR',
                    'source' => 'vicidial_database',
                    'server_id' => $server->getKey(),
                ],
            );
        } finally {
            if ($connection !== null) {
                $this->disconnect($connection);
            }
        }
    }

    public function normalizeRow(object $row, Campaign $campaign): HistoricalCallRecord
    {
        $sourceTable = (string) ($row->source_table ?? self::OUTBOUND_TABLE);
        $uniqueCallId = trim((string) ($row->unique_call_id ?? ''));
        if ($uniqueCallId === '') {
            $uniqueCallId = sha1(implode('|', [
                $sourceTable,
                (string) ($row->lead_id ?? ''),
                (string) ($row->call_date ?? ''),
                (string) ($row->phone_number ?? ''),
            ]));
        }
        $vicidialUser = trim((string) ($row->vicidial_user ?? '')) ?: null;

        return new HistoricalCallRecord(
            id: $sourceTable.':'.$uniqueCallId,
            uniqueCallId: $uniqueCallId,
            crmCampaignId: (int) $campaign->getKey(),
            crmCampaignCode: (string) $campaign->code,
            vicidialCampaignId: (string) ($row->vicidial_campaign_id ?? ''),
            vicidialListId: $this->nullableString($row->vicidial_list_id ?? null),
            leadId: $this->nullableInt($row->lead_id ?? null),
            vicidialUser: $vicidialUser,
            crmUserId: null,
            crmUserName: null,
            agentDisplayName: $vicidialUser ?? 'Unknown agent',
            phoneNumber: $this->nullableString($row->phone_number ?? null),
            callDate: $this->dateTime($row->call_date ?? null),
            callStartedAt: $this->epoch($row->start_epoch ?? null),
            callEndedAt: $this->epoch($row->end_epoch ?? null),
            callDirection: $sourceTable === self::INBOUND_TABLE ? 'INBOUND' : 'OUTBOUND',
            status: trim((string) ($row->raw_status ?? '')),
            dispositionCode: null,
            dispositionLabel: 'Unmapped',
            durationSeconds: $this->nullableInt($row->duration_seconds ?? null),
            talkSeconds: null,
            waitSeconds: $sourceTable === self::INBOUND_TABLE
                ? $this->nullableInt($row->wait_seconds ?? null)
                : null,
            rawEndReason: $this->nullableString($row->raw_end_reason ?? null),
            sourceTable: $sourceTable,
        );
    }

    /**
     * @param  array<int, string>  $campaignCodes
     * @param  array<string, mixed>  $filters
     */
    protected function combinedQuery(Connection $connection, array $campaignCodes, array $filters, bool $includeRecordFilters): Builder
    {
        $outbound = $this->sourceQuery($connection, self::OUTBOUND_TABLE, $campaignCodes, $filters, $includeRecordFilters);
        $inbound = $this->sourceQuery($connection, self::INBOUND_TABLE, $campaignCodes, $filters, $includeRecordFilters);

        return $connection->query()->fromSub($outbound->unionAll($inbound), 'historical_calls');
    }

    /**
     * @param  array<int, string>  $campaignCodes
     * @param  array<string, mixed>  $filters
     */
    protected function sourceQuery(
        Connection $connection,
        string $table,
        array $campaignCodes,
        array $filters,
        bool $includeRecordFilters,
    ): Builder {
        $columns = $table === self::INBOUND_TABLE
            ? 'uniqueid as unique_call_id, campaign_id as vicidial_campaign_id, list_id as vicidial_list_id, lead_id, user as vicidial_user, phone_number, call_date, start_epoch, end_epoch, length_in_sec as duration_seconds, status as raw_status, queue_seconds as wait_seconds, term_reason as raw_end_reason'
            : 'uniqueid as unique_call_id, campaign_id as vicidial_campaign_id, list_id as vicidial_list_id, lead_id, user as vicidial_user, phone_number, call_date, start_epoch, end_epoch, length_in_sec as duration_seconds, status as raw_status, NULL as wait_seconds, term_reason as raw_end_reason';
        $query = $connection->table($table)
            ->selectRaw($columns.", '".$table."' as source_table")
            ->whereIn('campaign_id', $campaignCodes);
        if ($filters['start_date'] !== null) {
            $query->where('call_date', '>=', $filters['start_date'].' 00:00:00');
        }
        if ($filters['end_date'] !== null) {
            $query->where('call_date', '<=', $filters['end_date'].' 23:59:59');
        }
        if ($filters['call_direction'] !== null) {
            $expectedTable = $filters['call_direction'] === 'INBOUND' ? self::INBOUND_TABLE : self::OUTBOUND_TABLE;
            if ($table !== $expectedTable) {
                $query->whereRaw('1 = 0');
            }
        }
        if (! $includeRecordFilters) {
            return $query;
        }
        if ($filters['agent'] !== null) {
            $query->where('user', 'like', '%'.$filters['agent'].'%');
        }
        if ($filters['statuses'] !== []) {
            $query->whereIn('status', $filters['statuses']);
        }
        if ($filters['phone'] !== null) {
            $query->where(function (Builder $phoneQuery) use ($filters): void {
                foreach ($this->phoneVariants($filters['phone']) as $variant) {
                    $phoneQuery->orWhere('phone_number', 'like', '%'.$variant.'%');
                }
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{start_date: ?string, end_date: ?string, agent: ?string, phone: ?string, statuses: array<int, string>, call_direction: ?string, sort_direction: string, sort: string}
     */
    protected function normalizeFilters(array $filters): array
    {
        return [
            'start_date' => $this->dateFilter($filters['start_date'] ?? null),
            'end_date' => $this->dateFilter($filters['end_date'] ?? null),
            'agent' => $this->nullableFilter($filters['agent'] ?? null),
            'phone' => $this->nullableFilter($filters['phone'] ?? null),
            'statuses' => array_values(array_filter(array_map(
                static fn (mixed $status): string => trim((string) $status),
                (array) ($filters['statuses'] ?? ($filters['status'] ?? [])),
            ))),
            'call_direction' => in_array(strtoupper((string) ($filters['direction'] ?? '')), ['INBOUND', 'OUTBOUND'], true)
                ? strtoupper((string) $filters['direction'])
                : null,
            'sort_direction' => strtolower((string) ($filters['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
            'sort' => (string) ($filters['sort'] ?? 'called_at'),
        ];
    }

    protected function makeConnection(VicidialServer $server): Connection
    {
        return DB::build([
            'driver' => 'mysql',
            'host' => (string) $server->db_host,
            'port' => (int) ($server->db_port ?: 3306),
            'database' => (string) ($server->db_name ?: 'asterisk'),
            'username' => (string) $server->db_username,
            'password' => (string) $server->db_password,
            'unix_socket' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [
                \PDO::ATTR_TIMEOUT => max(1, (int) config('vicidial.connect_timeout', 5)),
            ],
        ]);
    }

    protected function disconnect(Connection $connection): void
    {
        $connection->disconnect();
    }

    protected function logger(): TelephonyLogger
    {
        return app(TelephonyLogger::class);
    }

    protected function sortColumn(string $sort): string
    {
        return match ($sort) {
            'agent' => 'vicidial_user',
            'duration' => 'duration_seconds',
            'status' => 'raw_status',
            'vicidial_campaign' => 'vicidial_campaign_id',
            default => 'call_date',
        };
    }

    /**
     * @return array<int, string>
     */
    protected function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return [$phone];
        }
        $variants = [$digits];
        if (str_starts_with($digits, '09')) {
            $variants[] = '63'.substr($digits, 1);
            $variants[] = substr($digits, 1);
        } elseif (str_starts_with($digits, '639')) {
            $variants[] = '0'.substr($digits, 2);
            $variants[] = substr($digits, 2);
        } elseif (str_starts_with($digits, '9')) {
            $variants[] = '0'.$digits;
            $variants[] = '63'.$digits;
        }

        return array_values(array_unique($variants));
    }

    protected function dateFilter(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    protected function nullableFilter(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    protected function dateTime(mixed $value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value, (string) config('vicidial.report_timezone', config('app.timezone', 'UTC')));
        } catch (Throwable) {
            return null;
        }
    }

    protected function epoch(mixed $value): ?Carbon
    {
        if ($value === null || $value === '' || ! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        try {
            return Carbon::createFromTimestamp((int) $value, (string) config('vicidial.report_timezone', config('app.timezone', 'UTC')));
        } catch (Throwable) {
            return null;
        }
    }
}

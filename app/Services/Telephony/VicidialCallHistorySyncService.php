<?php

namespace App\Services\Telephony;

use App\Models\Campaign;
use App\Models\DispositionCode;
use App\Models\TelephonyCallHistory;
use App\Models\User;
use App\Models\VicidialCallHistorySyncState;
use Carbon\Carbon;
use Throwable;

class VicidialCallHistorySyncService
{
    public function __construct(
        protected VicidialHistoricalCallProvider $provider,
        protected CrmCampaignVicidialScopeResolver $scopeResolver,
        protected TelephonyLogger $logger,
    ) {}

    public function sync(Campaign|string $campaign, ?Carbon $from = null, ?Carbon $to = null): VicidialCallHistorySyncResult
    {
        $startedAt = microtime(true);
        $scope = $this->scopeResolver->resolve($campaign);
        $campaignModel = $scope->campaign;
        $campaignCodes = $scope->historicalCampaignCodes();

        if (! $campaignModel->exists || $scope->server === null || $campaignCodes === []) {
            return VicidialCallHistorySyncResult::failure(
                $scope->server === null ? 'not_configured' : 'no_campaigns_mapped',
                $scope->server === null
                    ? "No VICIdial server is configured for campaign '{$campaignModel->code}'."
                    : "No permitted VICIdial campaigns are mapped to CRM campaign '{$campaignModel->code}'.",
                meta: ['duration_ms' => $this->durationMs($startedAt)],
            );
        }

        $state = VicidialCallHistorySyncState::query()->firstOrCreate([
            'vicidial_server_id' => $scope->server->getKey(),
            'crm_campaign_id' => $campaignModel->getKey(),
        ], [
            'status' => VicidialCallHistorySyncState::STATUS_NEVER_SYNCED,
        ]);
        $window = $this->window($state, $from, $to);
        $state->update([
            'status' => VicidialCallHistorySyncState::STATUS_RUNNING,
            'last_started_at' => now(),
            'current_window_start' => $window['from'],
            'current_window_end' => $window['to'],
        ]);

        $providerResult = $this->provider->fetchRange(
            $scope->server,
            $campaignModel,
            $campaignCodes,
            $window['from'],
            $window['to'],
        );
        if (! $providerResult->success) {
            return $this->failed(
                $state,
                $providerResult->message ?? 'VICIdial call history is currently unavailable. Please try again.',
                (string) ($providerResult->meta['classification'] ?? 'REMOTE_DATABASE_ERROR'),
                (bool) ($providerResult->meta['retryable'] ?? true),
                $startedAt,
                $providerResult->meta,
            );
        }

        try {
            [$rowsInserted, $rowsUpdated] = $this->upsertRecords(
                $providerResult->records,
                $scope->server->getKey(),
                $campaignModel,
            );
            $latest = collect($providerResult->records)
                ->filter(fn (HistoricalCallRecord $record): bool => $record->callDate !== null)
                ->sortBy(fn (HistoricalCallRecord $record): int => $record->callDate?->getTimestamp() ?? 0)
                ->last();
            $checkpoint = $state->last_call_at;
            if ($latest instanceof HistoricalCallRecord && $latest->callDate !== null && ($checkpoint === null || $latest->callDate->greaterThan($checkpoint))) {
                $checkpoint = $latest->callDate;
            }
            if ($checkpoint === null || $window['to']->greaterThan($checkpoint)) {
                $checkpoint = $window['to'];
            }

            $state->update([
                'status' => VicidialCallHistorySyncState::STATUS_HEALTHY,
                'last_call_at' => $checkpoint,
                'last_unique_id' => $latest instanceof HistoricalCallRecord ? $latest->uniqueCallId : $state->last_unique_id,
                'last_successful_sync_at' => now(),
                'last_sync_duration_ms' => $this->durationMs($startedAt),
                'last_rows_received' => count($providerResult->records),
                'last_rows_inserted' => $rowsInserted,
                'last_rows_updated' => $rowsUpdated,
                'last_error_classification' => null,
                'last_error_message' => null,
                'current_window_start' => null,
                'current_window_end' => null,
            ]);
            $state->refresh();
            $meta = array_merge($providerResult->meta, [
                'duration_ms' => $this->durationMs($startedAt),
                'rows_received' => count($providerResult->records),
                'rows_inserted' => $rowsInserted,
                'rows_updated' => $rowsUpdated,
            ]);
            $this->logger->info('vicidial.call_history.sync', 'VICIdial call history synchronized locally.', [
                'server_id' => $scope->server->getKey(),
                'crm_campaign_id' => $campaignModel->getKey(),
                'crm_campaign' => (string) $campaignModel->code,
                ...$meta,
            ]);

            return VicidialCallHistorySyncResult::success(
                $state,
                count($providerResult->records),
                $rowsInserted,
                $rowsUpdated,
                $meta,
            );
        } catch (Throwable $exception) {
            return $this->failed(
                $state,
                'Local call history storage failed. Please try again.',
                'LOCAL_DATABASE_ERROR',
                true,
                $startedAt,
                ['error_class' => $exception::class],
            );
        }
    }

    /**
     * @param  array<int, HistoricalCallRecord>  $records
     * @return array{0: int, 1: int}
     */
    protected function upsertRecords(array $records, int $serverId, Campaign $campaign): array
    {
        if ($records === []) {
            return [0, 0];
        }

        $mappedUsers = $this->usersForViciLogins(array_map(
            static fn (HistoricalCallRecord $record): ?string => $record->vicidialUser,
            $records,
        ));
        $dispositionData = $this->dispositionData((string) $campaign->code);
        $inserted = 0;
        $updated = 0;

        foreach (array_chunk($records, max(1, (int) config('vicidial.call_history_sync.chunk_size', 500))) as $chunk) {
            $now = now()->toDateTimeString();
            $rows = array_map(function (HistoricalCallRecord $record) use ($serverId, $campaign, $mappedUsers, $dispositionData, $now): array {
                $status = strtoupper(trim($record->status));
                $disposition = $dispositionData['by_status'][$status] ?? null;
                $user = $record->vicidialUser !== null
                    ? ($mappedUsers[strtolower(trim($record->vicidialUser))] ?? null)
                    : null;

                return [
                    'vicidial_server_id' => $serverId,
                    'crm_campaign_id' => $campaign->getKey(),
                    'source_table' => $record->sourceTable,
                    'source_unique_id' => $record->uniqueCallId,
                    'vicidial_campaign_id' => $record->vicidialCampaignId !== '' ? $record->vicidialCampaignId : null,
                    'vicidial_list_id' => $record->vicidialListId,
                    'lead_id' => $record->leadId,
                    'vicidial_user' => $record->vicidialUser,
                    'crm_user_id' => $user?->getKey(),
                    'phone_number' => $record->phoneNumber,
                    'status' => $record->status !== '' ? $record->status : null,
                    'disposition_code' => $disposition['code'] ?? null,
                    'disposition_label' => $disposition['label'] ?? 'Unmapped',
                    'call_date' => $record->callDate?->toDateTimeString(),
                    'call_started_at' => $record->callStartedAt?->toDateTimeString(),
                    'call_ended_at' => $record->callEndedAt?->toDateTimeString(),
                    'duration_seconds' => $record->durationSeconds,
                    'talk_seconds' => $record->talkSeconds,
                    'wait_seconds' => $record->waitSeconds,
                    'direction' => $record->callDirection,
                    'raw_end_reason' => $record->rawEndReason,
                    'source_updated_at' => $record->callDate?->toDateTimeString(),
                    'synced_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $chunk);
            $identities = collect($rows)->map(fn (array $row): string => $row['source_table'].'|'.$row['source_unique_id'])->all();
            $existing = TelephonyCallHistory::query()
                ->where('vicidial_server_id', $serverId)
                ->where('crm_campaign_id', $campaign->getKey())
                ->whereIn('source_unique_id', array_values(array_unique(array_column($rows, 'source_unique_id'))))
                ->get(['source_table', 'source_unique_id'])
                ->mapWithKeys(fn (TelephonyCallHistory $history): array => [$history->source_table.'|'.$history->source_unique_id => true]);
            $chunkUpdated = count(array_filter($identities, static fn (string $identity): bool => isset($existing[$identity])));
            $updated += $chunkUpdated;
            $inserted += count($identities) - $chunkUpdated;

            TelephonyCallHistory::query()->upsert(
                $rows,
                ['vicidial_server_id', 'crm_campaign_id', 'source_table', 'source_unique_id'],
                [
                    'vicidial_campaign_id', 'vicidial_list_id', 'lead_id', 'vicidial_user', 'crm_user_id',
                    'phone_number', 'status', 'disposition_code', 'disposition_label', 'call_date',
                    'call_started_at', 'call_ended_at', 'duration_seconds', 'talk_seconds', 'wait_seconds',
                    'direction', 'raw_end_reason', 'source_updated_at', 'synced_at', 'updated_at',
                ],
            );
        }

        return [$inserted, $updated];
    }

    /**
     * @return array{from: Carbon, to: Carbon}
     */
    protected function window(VicidialCallHistorySyncState $state, ?Carbon $from, ?Carbon $to): array
    {
        $timezone = (string) config('vicidial.report_timezone', config('app.timezone', 'UTC'));
        $end = ($to ?? Carbon::now($timezone))->copy();
        $start = $from?->copy();
        if ($start === null) {
            $start = $state->last_call_at?->copy()->subMinutes((int) config('vicidial.call_history_sync.overlap_minutes', 5))
                ?? $end->copy()->subMinutes((int) config('vicidial.call_history_sync.recent_window_minutes', 15));
        }

        return ['from' => $start, 'to' => $end];
    }

    protected function failed(
        VicidialCallHistorySyncState $state,
        string $message,
        string $classification,
        bool $retryable,
        float $startedAt,
        array $meta = [],
    ): VicidialCallHistorySyncResult {
        $state->update([
            'status' => VicidialCallHistorySyncState::STATUS_FAILED,
            'last_failed_at' => now(),
            'last_sync_duration_ms' => $this->durationMs($startedAt),
            'last_error_classification' => $classification,
            'last_error_message' => $message,
        ]);
        $state->refresh();
        $meta = array_merge($meta, [
            'classification' => $classification,
            'duration_ms' => $this->durationMs($startedAt),
        ]);
        $this->logger->error('vicidial.call_history.sync', 'VICIdial call history synchronization failed.', [
            'sync_state_id' => $state->getKey(),
            'retryable' => $retryable,
            ...$meta,
        ]);

        return VicidialCallHistorySyncResult::failure('failed', $message, $retryable, $state, $meta);
    }

    /**
     * @param  array<int, ?string>  $logins
     * @return array<string, User>
     */
    protected function usersForViciLogins(array $logins): array
    {
        $logins = array_values(array_filter(array_map(
            static fn (?string $login): string => trim((string) $login),
            $logins,
        )));
        if ($logins === []) {
            return [];
        }

        $users = User::withTrashed()->where(function ($query) use ($logins): void {
            foreach (array_unique($logins) as $login) {
                $query->orWhereRaw('LOWER(vici_user) = ?', [strtolower($login)]);
            }
        })->get();

        $mapped = [];
        foreach ($users as $user) {
            $login = strtolower(trim((string) $user->vici_user));
            if ($login !== '' && ! isset($mapped[$login])) {
                $mapped[$login] = $user;
            }
        }

        return $mapped;
    }

    /**
     * @return array{by_status: array<string, array{code: string, label: string}>}
     */
    protected function dispositionData(string $campaignCode): array
    {
        $codes = DispositionCode::query()->active()->where(function ($query) use ($campaignCode): void {
            $query->where('campaign_code', $campaignCode)->orWhere('campaign_code', '');
        })->ordered()->get();
        $byCode = [];
        foreach ($codes as $code) {
            $key = strtoupper((string) $code->code);
            if ((string) $code->campaign_code === $campaignCode || ! isset($byCode[$key])) {
                $byCode[$key] = $code;
            }
        }

        $byStatus = [];
        foreach ($byCode as $code) {
            $status = strtoupper((string) (config('vicidial.disposition_map.'.strtoupper((string) $code->code)) ?? $code->code));
            $byStatus[$status] = ['code' => (string) $code->code, 'label' => (string) $code->label];
        }

        return ['by_status' => $byStatus];
    }

    protected function durationMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}

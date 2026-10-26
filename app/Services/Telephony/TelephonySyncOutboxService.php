<?php

namespace App\Services\Telephony;

use App\Jobs\ProcessTelephonySyncOutboxJob;
use App\Models\CallSession;
use App\Models\TelephonySyncOutbox;
use App\Models\User;
use App\Support\OperationResult;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelephonySyncOutboxService
{
    public const OPERATION_DISPOSITION = 'vicidial_disposition';

    public const OPERATION_LEAD_FIELDS = 'vicidial_lead_fields';

    /**
     * Persist only the minimum request data needed by a later worker.
     * Callers normally use this inside the local business transaction.
     */
    public function record(string $operationType, string $idempotencyKey, array $payload): TelephonySyncOutbox
    {
        if (! in_array($operationType, [self::OPERATION_DISPOSITION, self::OPERATION_LEAD_FIELDS], true)) {
            throw new \InvalidArgumentException('Unsupported telephony outbox operation.');
        }

        $encrypted = Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));

        return TelephonySyncOutbox::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'operation_type' => $operationType,
                'payload_encrypted' => $encrypted,
                'status' => TelephonySyncOutbox::STATUS_PENDING,
                'available_at' => now(),
            ],
        );
    }

    /** Dispatch due records. Duplicate queue jobs are safe because process() claims by row lock. */
    public function dispatchPending(int $limit = 100): int
    {
        $candidateIds = TelephonySyncOutbox::query()
            ->where('status', TelephonySyncOutbox::STATUS_PENDING)
            ->where(function ($query): void {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('last_dispatched_at')
                    ->orWhere('last_dispatched_at', '<=', now()->subMinutes(5));
            })
            ->orderBy('id')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        $dispatched = 0;
        foreach ($candidateIds as $id) {
            $claimedForDispatch = DB::transaction(function () use ($id): bool {
                $record = TelephonySyncOutbox::query()->whereKey($id)->lockForUpdate()->first();
                if (! $record || $record->status !== TelephonySyncOutbox::STATUS_PENDING) {
                    return false;
                }
                if ($record->available_at && $record->available_at->isFuture()) {
                    return false;
                }
                if ($record->last_dispatched_at && $record->last_dispatched_at->gt(now()->subMinutes(5))) {
                    return false;
                }

                $record->last_dispatched_at = now();
                $record->save();

                return true;
            });

            if (! $claimedForDispatch) {
                continue;
            }

            try {
                ProcessTelephonySyncOutboxJob::dispatch((int) $id);
                $dispatched++;
            } catch (Throwable $exception) {
                TelephonySyncOutbox::query()->whereKey($id)
                    ->where('status', TelephonySyncOutbox::STATUS_PENDING)
                    ->update(['last_dispatched_at' => null]);
                Log::channel('telephony')->warning('TelephonySyncOutbox dispatch failed', [
                    'outbox_id' => (int) $id,
                    'exception_class' => $exception::class,
                ]);
            }
        }

        return $dispatched;
    }

    /** A worker that disappears after claiming may already have sent the remote write. */
    public function expireStaleClaims(int $minutes = 5): int
    {
        return TelephonySyncOutbox::query()
            ->where('status', TelephonySyncOutbox::STATUS_PROCESSING)
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<=', now()->subMinutes(max(1, $minutes)))
            ->update([
                'status' => TelephonySyncOutbox::STATUS_UNKNOWN,
                'completed_at' => now(),
                'last_error_code' => 'WORKER_LOST_AFTER_CLAIM',
                'last_error_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function process(int $id): void
    {
        $record = DB::transaction(function () use ($id): ?TelephonySyncOutbox {
            $record = TelephonySyncOutbox::query()->whereKey($id)->lockForUpdate()->first();
            if (! $record || $record->status !== TelephonySyncOutbox::STATUS_PENDING) {
                return null;
            }
            if ($record->available_at && $record->available_at->isFuture()) {
                return null;
            }

            $record->status = TelephonySyncOutbox::STATUS_PROCESSING;
            $record->claimed_at = now();
            $record->attempt_count = (int) $record->attempt_count + 1;
            $record->save();

            return $record;
        });

        if (! $record) {
            return;
        }

        try {
            $payload = json_decode(Crypt::decryptString($record->payload_encrypted), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                $this->finish($record->id, TelephonySyncOutbox::STATUS_FAILED, 'INVALID_PAYLOAD');

                return;
            }
        } catch (Throwable) {
            $this->finish($record->id, TelephonySyncOutbox::STATUS_FAILED, 'PAYLOAD_DECRYPTION_FAILED');

            return;
        }

        try {
            $result = $this->execute($record, $payload);
        } catch (Throwable $exception) {
            // The operation boundary has been entered. Without a remote receipt,
            // the outcome cannot be proven safe to replay.
            $this->finish($record->id, TelephonySyncOutbox::STATUS_UNKNOWN, 'REMOTE_OUTCOME_UNKNOWN');
            Log::channel('telephony')->warning('TelephonySyncOutbox remote operation threw', [
                'outbox_id' => $record->id,
                'operation_type' => $record->operation_type,
                'exception_class' => $exception::class,
            ]);

            return;
        }

        if ($result->success) {
            $this->finish($record->id, TelephonySyncOutbox::STATUS_SYNCED);

            return;
        }

        $unknown = (bool) ($result->meta['outcome_unknown'] ?? false)
            || ($result->meta['failure_code'] ?? null) === 'VICIDIAL_OUTCOME_UNKNOWN'
            || ($result->meta['classification'] ?? null) === 'OUTCOME_UNKNOWN';
        $failureCode = $this->safeFailureCode($result);
        $this->finish(
            $record->id,
            $unknown ? TelephonySyncOutbox::STATUS_UNKNOWN : TelephonySyncOutbox::STATUS_FAILED,
            $failureCode,
        );
    }

    private function execute(TelephonySyncOutbox $record, array $payload): OperationResult
    {
        if ($record->operation_type === self::OPERATION_DISPOSITION) {
            $session = CallSession::query()->with('user')->find((int) ($payload['call_session_id'] ?? 0));
            if (! $session || $session->disposition_code === null) {
                return OperationResult::failure('Disposition session no longer exists.', null, ['failure_code' => 'SESSION_NOT_FOUND']);
            }

            return app(VicidialDispositionSyncService::class)->syncDispositionToVicidial($session);
        }

        if ($record->operation_type === self::OPERATION_LEAD_FIELDS) {
            $user = User::query()->find((int) ($payload['user_id'] ?? 0));
            if (! $user) {
                return OperationResult::failure('Agent account no longer exists.', null, ['failure_code' => 'USER_NOT_FOUND']);
            }

            return app(LeadService::class)->updateFields(
                $user,
                (string) ($payload['campaign'] ?? ''),
                (array) ($payload['fields'] ?? []),
            );
        }

        return OperationResult::failure('Unsupported outbox operation.', null, ['failure_code' => 'UNSUPPORTED_OPERATION']);
    }

    private function finish(int $id, string $status, ?string $errorCode = null): void
    {
        TelephonySyncOutbox::query()->whereKey($id)
            ->where('status', TelephonySyncOutbox::STATUS_PROCESSING)
            ->update([
                'status' => $status,
                'completed_at' => in_array($status, [TelephonySyncOutbox::STATUS_SYNCED, TelephonySyncOutbox::STATUS_FAILED, TelephonySyncOutbox::STATUS_UNKNOWN], true) ? now() : null,
                'last_error_code' => $errorCode,
                'last_error_at' => $errorCode ? now() : null,
                'updated_at' => now(),
            ]);
    }

    private function safeFailureCode(OperationResult $result): string
    {
        foreach (['failure_code', 'classification'] as $key) {
            $value = strtoupper((string) ($result->meta[$key] ?? ''));
            if ($value !== '' && preg_match('/^[A-Z0-9_]{1,80}$/', $value)) {
                return $value;
            }
        }

        return 'REMOTE_OPERATION_FAILED';
    }
}

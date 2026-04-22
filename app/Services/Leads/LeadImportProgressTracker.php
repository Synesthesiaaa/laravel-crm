<?php

namespace App\Services\Leads;

use Illuminate\Support\Facades\Cache;

class LeadImportProgressTracker
{
    public const CACHE_TTL_SECONDS = 86400;

    protected function key(string $runId): string
    {
        return 'lead_import_progress:'.$runId;
    }

    /**
     * Called immediately after the job is queued so the UI can poll before the worker picks it up.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function createQueued(string $runId, int $listId, int $userId, int $estimatedRows, ?array $meta = null): void
    {
        Cache::put($this->key($runId), [
            'run_id' => $runId,
            'list_id' => $listId,
            'user_id' => $userId,
            'status' => 'queued',
            'estimated_rows' => max(0, $estimatedRows),
            'rows_processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed_chunks' => 0,
            'recent' => [],
            'message' => null,
            'meta' => $meta,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], self::CACHE_TTL_SECONDS);
    }

    public function markProcessing(string $runId): void
    {
        $this->patch($runId, [
            'status' => 'processing',
            'message' => null,
        ]);
    }

    /**
     * @param  list<array{phone: string, name: ?string}>  $recent
     */
    public function afterChunk(
        string $runId,
        int $listId,
        int $chunkRowCount,
        array $recent,
        int $inserted,
        int $updated,
        int $skipped,
        int $failedChunks,
    ): void {
        $data = Cache::get($this->key($runId));
        if (! is_array($data) || (int) ($data['list_id'] ?? 0) !== $listId) {
            return;
        }

        $mergedRecent = $this->mergeRecent($data['recent'] ?? [], $recent);

        $estimated = max(0, (int) ($data['estimated_rows'] ?? 0));
        $processed = (int) ($data['rows_processed'] ?? 0) + max(0, $chunkRowCount);
        $percent = null;
        if ($estimated > 0) {
            $percent = min(100, round(($processed / $estimated) * 100, 1));
        }

        $data['rows_processed'] = $processed;
        $data['inserted'] = $inserted;
        $data['updated'] = $updated;
        $data['skipped'] = $skipped;
        $data['failed_chunks'] = $failedChunks;
        $data['recent'] = $mergedRecent;
        $data['percent'] = $percent;
        $data['updated_at'] = now()->toIso8601String();

        Cache::put($this->key($runId), $data, self::CACHE_TTL_SECONDS);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $runId): ?array
    {
        $data = Cache::get($this->key($runId));

        return is_array($data) ? $data : null;
    }

    public function complete(string $runId, int $inserted, int $updated, int $skipped, int $failedChunks): void
    {
        $data = Cache::get($this->key($runId));
        if (! is_array($data)) {
            return;
        }

        $estimated = max(0, (int) ($data['estimated_rows'] ?? 0));
        $processed = max((int) ($data['rows_processed'] ?? 0), $estimated);

        $data['status'] = 'completed';
        $data['inserted'] = $inserted;
        $data['updated'] = $updated;
        $data['skipped'] = $skipped;
        $data['failed_chunks'] = $failedChunks;
        $data['rows_processed'] = $processed;
        $data['percent'] = $estimated > 0 ? 100.0 : null;
        $data['message'] = null;
        $data['finished_at'] = now()->toIso8601String();
        $data['updated_at'] = now()->toIso8601String();

        Cache::put($this->key($runId), $data, self::CACHE_TTL_SECONDS);
    }

    public function fail(string $runId, string $message): void
    {
        $this->patch($runId, [
            'status' => 'failed',
            'message' => $message,
            'finished_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    protected function patch(string $runId, array $patch): void
    {
        $data = Cache::get($this->key($runId));
        if (! is_array($data)) {
            return;
        }
        $data = array_merge($data, $patch);
        Cache::put($this->key($runId), $data, self::CACHE_TTL_SECONDS);
    }

    /**
     * @param  list<array{phone?: string, name?: ?string}>  $existing
     * @param  list<array{phone: string, name: ?string}>  $incoming
     * @return list<array{phone: string, name: ?string}>
     */
    protected function mergeRecent(array $existing, array $incoming): array
    {
        $out = [];
        foreach (array_merge($existing, $incoming) as $row) {
            $phone = trim((string) ($row['phone'] ?? ''));
            if ($phone === '') {
                continue;
            }
            $name = isset($row['name']) && is_string($row['name']) ? trim($row['name']) : null;
            if ($name === '') {
                $name = null;
            }
            $out[] = ['phone' => $phone, 'name' => $name];
        }
        if (count($out) > 12) {
            $out = array_slice($out, -12);
        }

        return $out;
    }
}

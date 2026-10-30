<?php

namespace App\Services\Imports;

use App\Jobs\ProcessUserImport;
use App\Models\User;
use App\Models\UserImport;
use Illuminate\Database\ConcurrencyErrorDetector;
use Illuminate\Database\DeadlockException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UserImportService
{
    public function __construct(private CsvUserImporter $csv) {}

    public static function uploadLimitBytes(): int
    {
        $limits = [20 * 1024 * 1024];
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $limit = ini_parse_quantity((string) ini_get($setting));
            if ($limit > 0) {
                $limits[] = $setting === 'post_max_size' ? max(1, $limit - 8192) : $limit;
            }
        }

        return min($limits);
    }

    public function upload(User $owner, UploadedFile $file, bool $allowPrivileged): UserImport
    {
        try {
            $this->csv->validateHeader($file->getRealPath());
        } catch (InvalidUserImportInput $exception) {
            throw ValidationException::withMessages(['csv' => $exception->getMessage()]);
        }
        $id = (string) Str::uuid();
        $path = $id.'/source.csv';

        try {
            $file->storeAs($id, 'source.csv', 'user_imports');

            return DB::transaction(function () use ($owner, $file, $allowPrivileged, $id): UserImport {
                $run = UserImport::create([
                    'id' => $id, 'user_id' => $owner->id,
                    'file_name' => mb_substr(basename($file->getClientOriginalName()), 0, 255),
                    'source_digest' => hash_file('sha256', $file->getRealPath()),
                    'source_bytes' => $file->getSize(), 'allow_privileged_roles' => $allowPrivileged,
                    'expires_at' => now()->addDay(),
                ]);
                // This lane shares the CRM database: enqueue and import metadata commit together.
                Bus::dispatch(new ProcessUserImport($run->id, 'preview'));

                return $run->refresh();
            });
        } catch (\Throwable) {
            Storage::disk('user_imports')->delete($path);

            throw ValidationException::withMessages(['csv' => 'The upload could not be queued. Please try again or contact your administrator.']);
        }
    }

    public function start(UserImport $run): UserImport
    {
        return DB::transaction(function () use ($run): UserImport {
            $run = UserImport::lockForUpdate()->findOrFail($run->id);
            abort_unless($run->status === 'ready' && $run->accepted > 0 && $run->expires_at->isFuture(), 409, 'This preview is not ready to import. Upload a new CSV if it has expired.');
            $run->update([
                'phase' => 'import', 'status' => 'queued', 'cursor_offset' => 0, 'cursor_record' => 1,
                'preview_counts' => $run->only(['processed', 'accepted', 'skipped', 'rejected']),
                'processed' => 0, 'accepted' => 0, 'skipped' => 0, 'rejected' => 0, 'message' => null,
            ]);
            Bus::dispatch(new ProcessUserImport($run->id, 'import'));

            return $run;
        });
    }

    public function cancel(UserImport $run): UserImport
    {
        DB::transaction(function () use ($run): void {
            $run = UserImport::lockForUpdate()->findOrFail($run->id);
            if (! $run->isTerminal()) {
                $run->update(['status' => 'cancelled', 'message' => 'Import cancelled. Accounts already created are retained.']);
                $this->forgetIdentities($run);
            }
        });
        $run->refresh();
        $this->cleanup($run);

        return $run;
    }

    /** Returns true only when the same queue job should release for another slice. */
    public function process(string $id, string $phase): bool
    {
        try {
            $more = DB::transaction(function () use ($id, $phase): bool {
                $run = UserImport::lockForUpdate()->find($id);
                if (! $run || $run->phase !== $phase || ! in_array($run->status, ['queued', 'processing'], true)) {
                    return false;
                }
                if ($run->expires_at->isPast()) {
                    $run->update(['status' => 'expired', 'message' => 'This upload expired. Upload the CSV again to continue.']);
                    $this->forgetIdentities($run);

                    return false;
                }
                if (! User::whereKey($run->user_id)->where('role', User::ROLE_SUPER_ADMIN)->exists()) {
                    $run->update(['status' => 'failed', 'message' => 'Import stopped because the submitting account no longer has Super Admin access.']);
                    $this->forgetIdentities($run);

                    return false;
                }
                $path = Storage::disk('user_imports')->path($run->sourcePath());
                if ($run->cursor_offset === 0 && (! is_file($path) || ! hash_equals($run->source_digest, hash_file('sha256', $path)))) {
                    throw new RuntimeException('Source unavailable.');
                }
                $errors = [];
                $counts = $this->csv->import($path, 50, $phase === 'preview', $run->allow_privileged_roles,
                    function (int $record, string $message) use (&$errors, $run, $phase): void {
                        $errors[] = ['import_id' => $run->id, 'phase' => $phase, 'record' => $record, 'errors' => $this->formatError($message)];
                    },
                    static function (): void {},
                    new UserImportBatch($run->cursor_offset, $run->cursor_record, 50, new DatabaseUserImportIdentityStore($run->id)),
                );
                if ($errors !== []) {
                    DB::table('user_import_errors')->insert($errors);
                }
                $position = $this->csv->position();
                $run->fill([
                    'status' => $position['complete'] ? ($phase === 'preview' ? 'ready' : 'completed') : 'processing',
                    'cursor_offset' => $position['offset'], 'cursor_record' => $position['record'],
                    'processed' => $run->processed + $counts['processed'], 'accepted' => $run->accepted + $counts['created'],
                    'skipped' => $run->skipped + $counts['skipped'], 'rejected' => $run->rejected + $counts['rejected'],
                ])->save();
                if ($position['complete']) {
                    $this->forgetIdentities($run);
                }

                return ! $position['complete'];
            });
            if (! $more && ($run = UserImport::find($id))) {
                $this->cleanup($run);
            }

            return $more;
        } catch (\Throwable $exception) {
            // Never pass the original exception to Laravel's failed-job storage or logs:
            // a database exception may contain plaintext password/telephony bindings.
            if ($exception instanceof DeadlockException || app(ConcurrencyErrorDetector::class)->causedByConcurrencyError($exception)) {
                // The entire slice rolled back, including its identities and cursor.
                // Release the job rather than failing a recoverable locking conflict.
                return true;
            }
            $this->fail($id, $phase);

            return false;
        }
    }

    public function fail(string $id, string $phase): void
    {
        try {
            DB::transaction(function () use ($id, $phase): void {
                $run = UserImport::lockForUpdate()->find($id);
                if ($run && $run->phase === $phase && ! $run->isTerminal()) {
                    $run->update(['status' => 'failed', 'message' => 'Import stopped. Previously completed accounts are retained. Upload the CSV again to retry, or contact your administrator.']);
                    $this->forgetIdentities($run);
                }
            });
            if ($run = UserImport::find($id)) {
                $this->cleanup($run);
            }
        } catch (\Throwable) {
            throw new RuntimeException('User import is unavailable. Check database and private storage access.');
        }
    }

    public function present(UserImport $run): array
    {
        $active = in_array($run->status, ['queued', 'processing'], true);

        return [
            ...$run->only(['id', 'file_name', 'phase', 'status', 'processed', 'accepted', 'skipped', 'rejected', 'message', 'preview_counts']),
            'percent' => min(100, (int) floor($run->cursor_offset / max(1, $run->source_bytes) * 100)),
            'can_start' => $run->status === 'ready' && $run->accepted > 0 && $run->expires_at->isFuture(),
            'can_cancel' => ! $run->isTerminal(), 'active' => $active,
            'waiting' => $active && $run->updated_at->lt(now()->subMinute()),
            'expires_at' => $run->expires_at->toIso8601String(),
            'errors' => DB::table('user_import_errors')->where('import_id', $run->id)->where('phase', $run->phase)->orderBy('id')->limit(10)->get(['record', 'errors'])->all(),
            'urls' => [
                'status' => route('admin.user-imports.show', $run), 'start' => route('admin.user-imports.start', $run),
                'cancel' => route('admin.user-imports.cancel', $run), 'report' => route('admin.user-imports.report', $run),
            ],
        ];
    }

    public function prune(): void
    {
        UserImport::where(fn ($query) => $query->where('expires_at', '<=', now())->orWhereIn('status', ['completed', 'failed', 'cancelled', 'expired']))
            ->chunkById(100, function ($runs): void {
                foreach ($runs as $candidate) {
                    DB::transaction(function () use ($candidate): void {
                        $run = UserImport::lockForUpdate()->find($candidate->id);
                        if ($run && ! $run->isTerminal() && $run->expires_at->isPast()) {
                            $run->update(['status' => 'expired', 'message' => 'This upload expired. Upload the CSV again to continue.']);
                            $this->forgetIdentities($run);
                        }
                    });
                    if ($current = $candidate->fresh()) {
                        $this->cleanup($current);
                    }
                }
            });
        UserImport::where('created_at', '<', now()->subDays(7))->whereIn('status', ['completed', 'failed', 'cancelled', 'expired'])->delete();
        $disk = Storage::disk('user_imports');
        foreach ($disk->allFiles() as $path) {
            if ($disk->lastModified($path) < now()->subDay()->timestamp) {
                $disk->delete($path);
            }
        }
    }

    private function forgetIdentities(UserImport $run): void
    {
        DB::table('user_import_identities')->where('import_id', $run->id)->delete();
    }

    private function formatError(string $message): string
    {
        $message = str_replace('Non-Agent roles require --allow-privileged-roles.', 'Enable Allow roles above Agent before uploading this file.', $message);
        if (str_starts_with($message, 'password:')) {
            return 'The password must be present, fit the supported length, and contain at least 8 characters with uppercase, lowercase, and a number.';
        }

        return $message;
    }

    private function cleanup(UserImport $run): void
    {
        if ($run->isTerminal()) {
            Storage::disk('user_imports')->delete($run->sourcePath());
        }
    }
}

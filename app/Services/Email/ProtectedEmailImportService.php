<?php

namespace App\Services\Email;

use App\Jobs\PrepareProtectedEmailImport;
use App\Models\ProtectedEmailImport;
use App\Models\User;
use App\Services\EmailCampaignDraftService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProtectedEmailImportService
{
    private const CHUNK_BYTES = 1048576;

    public function __construct(private EmailCampaignDraftService $drafts) {}

    public function stage(User $actor, array $data, UploadedFile $csv, array $personal = [], ?UploadedFile $zip = null, array $shared = []): ProtectedEmailImport
    {
        abort_unless($actor->isSuperAdmin(), 403);
        $id = (string) Str::uuid();
        $entries = [['kind' => 'csv', 'file' => $csv]];
        foreach ($personal as $file) {
            $entries[] = ['kind' => 'personal', 'file' => $file];
        }
        foreach ($shared as $file) {
            $entries[] = ['kind' => 'shared', 'file' => $file];
        }
        if ($zip) {
            $entries[] = ['kind' => 'zip', 'file' => $zip];
        }
        if (count($personal) + count($shared) > (int) config('email_campaigns.max_files', 500) || ($zip && $personal)) {
            $this->invalid('Choose a ZIP or bounded individual PDFs, with no duplicate personal-upload source.');
        }
        $total = 0;
        $manifest = [];

        try {
            foreach ($entries as $entry) {
                $file = $entry['file'];
                if (! $file instanceof UploadedFile) {
                    $this->invalid('Every staged source must be a valid uploaded file.');
                }
                $maximum = match ($entry['kind']) {
                    'csv' => 5242880,
                    'zip' => (int) config('email_campaigns.max_total_bytes', 104857600),
                    default => (int) config('email_campaigns.max_file_bytes', 10485760),
                };
                $bytes = (int) $file->getSize();
                if ($bytes < 1 || $bytes > $maximum) {
                    $this->invalid('An upload is empty or exceeds its configured size limit.');
                }
                if ($entry['kind'] !== 'csv') {
                    $total += $bytes;
                    if ($total > (int) config('email_campaigns.max_total_bytes', 104857600)) {
                        $this->invalid('Combined uploaded document bytes exceed the configured limit.');
                    }
                }
                $manifest[] = $this->encryptFile($id, $entry['kind'], $file);
            }

            return DB::transaction(function () use ($actor, $data, $id, $manifest, $total): ProtectedEmailImport {
                $run = ProtectedEmailImport::create([
                    'id' => $id, 'user_id' => $actor->id,
                    'payload' => ['data' => array_intersect_key($data, array_flip(['name', 'email_template_id', 'batch_size', 'delay_seconds', 'purpose', 'attachment_mode', 'confirmed_permission'])), 'files' => $manifest],
                    'file_count' => count($manifest), 'source_bytes' => $total + $manifest[0]['bytes'],
                    'expires_at' => now()->addDay(),
                ]);
                PrepareProtectedEmailImport::dispatch($id)->afterCommit();
                activity('email-campaign')->causedBy($actor)->withProperties(['import_id' => $id, 'files' => count($manifest)])->log('email_campaign.protected_import_staged');

                return $run->refresh();
            });
        } catch (\Throwable $error) {
            // A queue insertion can fail after the durable import row committed.
            if (ProtectedEmailImport::whereKey($id)->exists()) {
                ProtectedEmailImport::whereKey($id)->where('status', 'queued')->update(['status' => 'failed', 'error' => 'The preparation queue is unavailable. Restore it and resume this import.']);
                $this->invalid('Your encrypted upload was saved but could not be queued. Open the import history and resume it after restoring the preparation worker.');
            }
            $this->deleteStaged($id);

            throw $error;
        }
    }

    public function process(string $id): void
    {
        $run = DB::transaction(function () use ($id): ?ProtectedEmailImport {
            $run = ProtectedEmailImport::lockForUpdate()->find($id);
            if (! $run || in_array($run->status, ['ready', 'cancelled', 'expired'], true)) {
                return null;
            }
            if ($run->status === 'processing' && $run->lease_expires_at?->isFuture()) {
                return null;
            }
            if ($run->expires_at->isPast()) {
                $run->update(['status' => 'expired', 'payload' => [], 'error' => 'Encrypted staging expired. Upload the authorized source again.']);
                $this->deleteStaged($run->id);

                return null;
            }
            if (! User::whereKey($run->user_id)->where('role', User::ROLE_SUPER_ADMIN)->exists()) {
                $run->update(['status' => 'failed', 'error' => 'The submitting account no longer has Super Admin access.']);

                return null;
            }
            $run->forceFill(['status' => 'processing', 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => now()->addMinutes(10), 'heartbeat_at' => now(), 'attempts' => $run->attempts + 1, 'prepared_files' => 0, 'error' => null])->save();

            return $run;
        }, 3);
        if (! $run) {
            return;
        }
        $workspace = null;
        $documentKeys = [];

        try {
            $root = storage_path('app/private/email-preparation');
            if (! is_dir($root) && ! mkdir($root, 0700, true) && ! is_dir($root)) {
                throw new \RuntimeException('Private preparation root is unavailable.');
            }
            $workspace = $root.'/'.Str::uuid();
            if (! mkdir($workspace, 0700)) {
                throw new \RuntimeException('Private preparation workspace is unavailable.');
            }
            file_put_contents($workspace.'/lease.json', json_encode(['import_id' => $run->id, 'lease_token' => $run->lease_token], JSON_THROW_ON_ERROR));
            chmod($workspace.'/lease.json', 0600);
            $files = ['personal' => [], 'shared' => [], 'csv' => null, 'zip' => null];
            foreach ($run->payload['files'] ?? [] as $entry) {
                if (! $this->leaseActive($run)) {
                    return;
                }
                $file = $this->decryptFile($run, $entry, $workspace);
                if (in_array($entry['kind'], ['personal', 'shared'], true)) {
                    $files[$entry['kind']][] = $file;
                } else {
                    $files[$entry['kind']] = $file;
                }
                ProtectedEmailImport::whereKey($run->id)->where('status', 'processing')->where('lease_token', $run->lease_token)->increment('prepared_files', 1, ['heartbeat_at' => now()]);
            }
            if (! $files['csv']) {
                $this->invalid('The encrypted recipient manifest is unavailable.');
            }
            DB::transaction(function () use ($run, $files, &$documentKeys): void {
                $locked = ProtectedEmailImport::lockForUpdate()->findOrFail($run->id);
                if ($locked->status !== 'processing' || ! hash_equals((string) $locked->lease_token, (string) $run->lease_token)) {
                    return;
                }
                $actor = User::whereKey($locked->user_id)->where('role', User::ROLE_SUPER_ADMIN)->first();
                if (! $actor || $locked->expires_at->isPast()) {
                    $this->invalid('The submitting account or staged source is no longer eligible.');
                }
                // Draft creation and the import link commit together. A replay cannot create a second draft.
                $result = $this->drafts->create($actor, $locked->payload['data'], $files['csv'], $files['personal'], $files['zip'], $files['shared']);
                $campaign = $result['campaign'];
                $documentKeys = $campaign->documents()->pluck('storage_key')->all();
                $locked->forceFill(['status' => 'ready', 'email_campaign_id' => $campaign->id, 'lease_token' => null, 'lease_expires_at' => null, 'heartbeat_at' => now(), 'payload' => [], 'error' => null])->save();
                activity('email-campaign')->causedBy($actor)->withProperties(['import_id' => $locked->id, 'campaign_id' => $campaign->id])->log('email_campaign.protected_import_ready');
            }, 3);
            if (ProtectedEmailImport::whereKey($run->id)->where('status', 'ready')->exists()) {
                $this->deleteStaged($run->id);
            }
        } catch (ValidationException $error) {
            // These errors originate only in the bounded CSV/PDF validators; no transport is invoked here.
            $detail = collect($error->errors())->flatten()->filter(fn ($message) => is_string($message))->first();
            $this->failLease($run, mb_substr($detail ?: 'Source validation failed. Correct the CSV/PDF mapping or policy and upload a replacement.', 0, 500));
            if ($documentKeys) {
                Storage::disk('email_documents')->delete($documentKeys);
            }
        } catch (DecryptException $error) {
            $this->failLease($run, 'Source validation or integrity verification failed. Check the original CSV/PDF mapping and policies, then upload a corrected replacement.');
            if ($documentKeys) {
                Storage::disk('email_documents')->delete($documentKeys);
            }
        } catch (\Throwable $error) {
            $this->failLease($run, 'Preparation was interrupted before approval. Restore the preparation worker and resume this import.');
            if ($documentKeys) {
                Storage::disk('email_documents')->delete($documentKeys);
            }

            throw $error;
        } finally {
            if ($workspace) {
                $this->deleteWorkspace($workspace);
            }
        }
    }

    public function authorize(User $actor, ProtectedEmailImport $run): void
    {
        abort_unless($actor->isSuperAdmin() && (int) $run->user_id === (int) $actor->id, 404);
    }

    public function cancel(User $actor, ProtectedEmailImport $run): ProtectedEmailImport
    {
        DB::transaction(function () use ($actor, $run): void {
            $run = ProtectedEmailImport::lockForUpdate()->findOrFail($run->id);
            $this->authorize($actor, $run);
            abort_if($run->status === 'ready', 409, 'The draft is already prepared. Cancel it from its review page.');
            $run->update(['status' => 'cancelled', 'payload' => [], 'lease_token' => null, 'lease_expires_at' => null, 'error' => null]);
            $this->deleteStaged($run->id);
            activity('email-campaign')->causedBy($actor)->withProperties(['import_id' => $run->id])->log('email_campaign.protected_import_cancelled');
        });

        return $run->refresh();
    }

    public function resume(User $actor, ProtectedEmailImport $run): ProtectedEmailImport
    {
        return DB::transaction(function () use ($actor, $run): ProtectedEmailImport {
            $run = ProtectedEmailImport::lockForUpdate()->findOrFail($run->id);
            $this->authorize($actor, $run);
            abort_unless($run->status === 'failed' && $run->expires_at->isFuture() && ! $run->email_campaign_id && ! empty($run->payload['files']), 409, 'Only an unexpired interrupted import can resume; validation errors require corrected sources.');
            $run->update(['status' => 'queued', 'error' => null, 'lease_token' => null, 'lease_expires_at' => null]);
            PrepareProtectedEmailImport::dispatch($run->id)->afterCommit();

            return $run;
        });
    }

    public function interrupted(string $id): void
    {
        ProtectedEmailImport::whereKey($id)->where('status', 'processing')->where('lease_expires_at', '<=', now())->update(['status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null, 'error' => 'The preparation worker stopped. Resume this unapproved import.']);
    }

    public function present(ProtectedEmailImport $run): array
    {
        return [...$run->only(['id', 'status', 'email_campaign_id', 'file_count', 'prepared_files', 'source_bytes', 'attempts', 'error']), 'expires_at' => $run->expires_at?->toIso8601String(), 'heartbeat_at' => $run->heartbeat_at?->toIso8601String(), 'review_url' => $run->email_campaign_id ? route('admin.email-campaigns.review', $run->email_campaign_id) : null];
    }

    /** Expire staged material and redispatch interrupted preparation, without sending mail. */
    public function maintain(): array
    {
        $expired = $recovered = 0;
        foreach (ProtectedEmailImport::whereNotIn('status', ['expired', 'cancelled', 'ready'])->where('expires_at', '<=', now())->lazyById(100, 'id') as $run) {
            DB::transaction(function () use ($run, &$expired): void {
                $locked = ProtectedEmailImport::lockForUpdate()->find($run->id);
                if (! $locked || $locked->expires_at->isFuture() || ($locked->status === 'processing' && $locked->lease_expires_at?->isFuture())) {
                    return;
                }
                $locked->update(['status' => 'expired', 'payload' => [], 'lease_token' => null, 'lease_expires_at' => null, 'error' => 'Encrypted staging expired. Upload the authorized source again.']);
                $this->deleteStaged($locked->id);
                $expired++;
            });
        }
        foreach (ProtectedEmailImport::where('status', 'processing')->where('lease_expires_at', '<=', now())->where('expires_at', '>', now())->lazyById(100, 'id') as $run) {
            DB::transaction(function () use ($run, &$recovered): void {
                $locked = ProtectedEmailImport::lockForUpdate()->find($run->id);
                if (! $locked || $locked->status !== 'processing' || $locked->lease_expires_at?->isFuture()) {
                    return;
                }
                $locked->update(['status' => 'queued', 'lease_token' => null, 'lease_expires_at' => null, 'error' => null]);
                PrepareProtectedEmailImport::dispatch($locked->id)->afterCommit();
                $recovered++;
            });
        }
        $root = storage_path('app/private/email-preparation');
        foreach (glob($root.'/*') ?: [] as $workspace) {
            if (! is_dir($workspace) || is_link($workspace) || ! Str::isUuid(basename($workspace)) || filemtime($workspace) > time() - 660) {
                continue;
            }
            $lease = is_file($workspace.'/lease.json') ? json_decode(file_get_contents($workspace.'/lease.json'), true) : [];
            $active = ProtectedEmailImport::whereKey($lease['import_id'] ?? '')->where('status', 'processing')->where('lease_token', $lease['lease_token'] ?? '')->where('lease_expires_at', '>', now())->exists();
            if (! $active) {
                $this->deleteWorkspace($workspace);
            }
        }

        return compact('expired', 'recovered');
    }

    private function encryptFile(string $importId, string $kind, UploadedFile $file): array
    {
        $fileId = (string) Str::uuid();
        $input = fopen($file->getRealPath(), 'rb');
        if (! $input) {
            throw new \RuntimeException('Upload source could not be opened.');
        }
        $hash = hash_init('sha256');
        $chunks = $bytes = 0;

        try {
            while (! feof($input)) {
                $chunk = fread($input, self::CHUNK_BYTES);
                if ($chunk === false) {
                    throw new \RuntimeException('Upload source could not be read.');
                }
                if ($chunk === '') {
                    break;
                }
                hash_update($hash, $chunk);
                $bytes += strlen($chunk);
                $encrypted = Crypt::encryptString($importId."\n".$fileId."\n".$chunks."\n".$chunk);
                if (! Storage::disk('local')->put($this->chunkPath($importId, $fileId, $chunks), $encrypted)) {
                    throw new \RuntimeException('Private encrypted staging is unavailable.');
                }
                $chunks++;
            }
        } finally {
            fclose($input);
        }
        if ($bytes !== $file->getSize()) {
            $this->invalid('An upload changed during private staging.');
        }

        return ['id' => $fileId, 'kind' => $kind, 'name' => $file->getClientOriginalName(), 'mime' => $file->getClientMimeType(), 'bytes' => $bytes, 'chunks' => $chunks, 'sha256' => hash_final($hash)];
    }

    private function decryptFile(ProtectedEmailImport $run, array $entry, string $workspace): UploadedFile
    {
        if (! Str::isUuid($entry['id'] ?? '') || ! in_array($entry['kind'] ?? '', ['csv', 'personal', 'shared', 'zip'], true) || ($entry['chunks'] ?? 0) < 1 || $entry['chunks'] > 101) {
            $this->invalid('The encrypted upload manifest is invalid.');
        }
        $path = $workspace.'/'.$entry['id'].'.source';
        $output = fopen($path, 'xb');
        if (! $output) {
            throw new \RuntimeException('Private worker workspace is unavailable.');
        }
        chmod($path, 0600);
        $hash = hash_init('sha256');
        $bytes = 0;

        try {
            for ($index = 0; $index < $entry['chunks']; $index++) {
                if (! $this->leaseActive($run)) {
                    throw ValidationException::withMessages(['import' => 'Preparation stopped.']);
                }
                $plain = Crypt::decryptString(Storage::disk('local')->get($this->chunkPath($run->id, $entry['id'], $index)));
                $parts = explode("\n", $plain, 4);
                if (count($parts) !== 4 || ! hash_equals($run->id, $parts[0]) || ! hash_equals($entry['id'], $parts[1]) || $parts[2] !== (string) $index || strlen($parts[3]) > self::CHUNK_BYTES) {
                    $this->invalid('An encrypted upload chunk failed ownership verification.');
                }
                hash_update($hash, $parts[3]);
                $bytes += strlen($parts[3]);
                if (fwrite($output, $parts[3]) !== strlen($parts[3])) {
                    throw new \RuntimeException('Private worker workspace write failed.');
                }
                unset($plain, $parts);
            }
        } finally {
            fclose($output);
        }
        if ($bytes !== $entry['bytes'] || ! hash_equals($entry['sha256'], hash_final($hash))) {
            $this->invalid('The encrypted upload digest failed verification.');
        }

        return new UploadedFile($path, $entry['name'], $entry['mime'], null, true);
    }

    private function leaseActive(ProtectedEmailImport $run): bool
    {
        return ProtectedEmailImport::whereKey($run->id)->where('status', 'processing')->where('lease_token', $run->lease_token)->where('lease_expires_at', '>', now())->exists();
    }

    private function failLease(ProtectedEmailImport $run, string $message): void
    {
        ProtectedEmailImport::whereKey($run->id)->where('status', 'processing')->where('lease_token', $run->lease_token)->update(['status' => 'failed', 'lease_token' => null, 'lease_expires_at' => null, 'error' => $message]);
    }

    private function chunkPath(string $id, string $file, int $chunk): string
    {
        return 'email-protected-imports/'.$id.'/'.$file.'/'.$chunk.'.enc';
    }

    private function deleteStaged(string $id): void
    {
        if (Str::isUuid($id)) {
            Storage::disk('local')->deleteDirectory('email-protected-imports/'.$id);
        }
    }

    private function deleteWorkspace(string $workspace): void
    {
        $root = realpath(storage_path('app/private/email-preparation'));
        $resolved = realpath($workspace);
        if (! $root || ! $resolved || is_link($workspace) || ! str_starts_with(strtolower($resolved), strtolower($root.DIRECTORY_SEPARATOR)) || ! Str::isUuid(basename($resolved))) {
            return;
        }
        foreach (glob($resolved.'/*') ?: [] as $path) {
            if (is_file($path) && ! is_link($path)) {
                unlink($path);
            }
        }
        rmdir($resolved);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['import' => $message]);
    }
}

<?php

namespace App\Services;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class EmailCampaignAttachmentService
{
    public function __construct(private EmailPdfInspector $pdfs) {}

    public static function validateFilename(string $name): void
    {
        if ($name === '' || strlen($name) > 255 || ! mb_check_encoding($name, 'UTF-8')
            || preg_match('/[\x00-\x1F\x7F\/\\\\:<>"|?*]/u', $name)
            || str_contains($name, '..') || str_starts_with($name, '.') || preg_match('/^[=+@-]/u', $name)
            || ! str_ends_with(strtolower($name), '.pdf')) {
            throw ValidationException::withMessages(['documents' => 'Use unique root-level PDF filenames without paths or unsafe characters.']);
        }
    }

    /** Store one bounded encrypted blob at a time; never extract archive names to disk. */
    public function ingest(EmailCampaign $campaign, array $personal, ?UploadedFile $zip, array $shared, int $actor): void
    {
        $keys = [];
        $temp = null;
        $archive = null;
        $opened = false;

        try {
            if ($zip && $personal) {
                $this->invalid('Choose a ZIP or multiple personal PDFs, not both.');
            }
            $sources = [];
            foreach ($personal as $file) {
                $sources[] = [$file->getClientOriginalName(), $file->getRealPath(), false];
            }
            foreach ($shared as $file) {
                $sources[] = [$file->getClientOriginalName(), $file->getRealPath(), true];
            }
            $total = 0;
            if ($zip) {
                $archive = new ZipArchive;
                $opened = $archive->open($zip->getRealPath(), ZipArchive::RDONLY) === true;
                if (! $opened || $archive->numFiles > config('email_campaigns.max_files')) {
                    $this->invalid('ZIP is invalid or has too many files.');
                }
                // Validate the entire directory before reading uncompressed content.
                $names = [];
                for ($i = 0; $i < $archive->numFiles; $i++) {
                    $stat = $archive->statIndex($i);
                    $name = $stat['name'];
                    self::validateFilename($name);
                    $archive->getExternalAttributesIndex($i, $opsys, $attributes);
                    $kind = ($attributes >> 16) & 0170000;
                    if (($opsys === ZipArchive::OPSYS_UNIX && $kind !== 0 && $kind !== 0100000)
                        || ($stat['encryption_method'] ?? 0) !== 0
                        || ! in_array($stat['comp_method'], [ZipArchive::CM_STORE, ZipArchive::CM_DEFLATE], true)
                        || isset($names[mb_strtolower($name)]) || $stat['size'] <= 0
                        || $stat['size'] > config('email_campaigns.max_file_bytes')
                        || $stat['size'] / max(1, $stat['comp_size']) > 200) {
                        $this->invalid('ZIP contains an unsafe, duplicate, encrypted or oversized entry.');
                    }
                    $names[mb_strtolower($name)] = true;
                    $total += $stat['size'];
                    if ($total > config('email_campaigns.max_total_bytes')) {
                        $this->invalid('Uncompressed document limit exceeded.');
                    }
                    $sources[] = [$name, $i, false];
                }
                $temp = storage_path('app/private/email-imports/'.Str::uuid());
                if (! mkdir($temp, 0700, true)) {
                    $this->invalid('Cannot prepare private import workspace.');
                }
            }
            if (count($sources) > config('email_campaigns.max_files')) {
                $this->invalid('Document count limit exceeded.');
            }
            $recipients = $campaign->recipients()->get()->keyBy('pdf_filename');
            $seen = $mapped = [];
            $total = 0;
            foreach ($sources as [$name, $source, $isShared]) {
                self::validateFilename($name);
                $key = mb_strtolower($name);
                if (isset($seen[$key])) {
                    $this->invalid('Duplicate or case-colliding PDF filenames.');
                }
                $seen[$key] = true;
                $recipient = $isShared ? null : $recipients->get($name);
                if (! $isShared && ! $recipient) {
                    $this->invalid('An uploaded PDF has no exact recipient mapping.');
                }
                $path = $source;
                if (is_int($source)) {
                    $path = $temp.'/'.Str::uuid().'.pdf';
                    $input = $archive->getStreamIndex($source);
                    $output = fopen($path, 'xb');
                    chmod($path, 0600);

                    try {
                        $copied = stream_copy_to_stream($input, $output, config('email_campaigns.max_file_bytes') + 1);
                    } finally {
                        if (is_resource($input)) {
                            fclose($input);
                        }
                        fclose($output);
                    }
                    if ($copied !== $archive->statIndex($source)['size']) {
                        $this->invalid('ZIP entry could not be verified.');
                    }
                }
                $size = filesize($path);
                $total += $size;
                if ($size <= 0 || $size > config('email_campaigns.max_file_bytes') || $total > config('email_campaigns.max_total_bytes')) {
                    $this->invalid('Document size limit exceeded.');
                }
                $this->pdfs->inspect($path);
                $storageKey = $campaign->id.'/'.Str::uuid().'.enc';
                $keys[] = $storageKey;
                Storage::disk('email_documents')->put($storageKey, Crypt::encryptString(file_get_contents($path)));
                $campaign->documents()->create([
                    'email_campaign_recipient_id' => $recipient?->id,
                    'uploaded_by_user_id' => $actor, 'filename' => $name, 'storage_key' => $storageKey,
                    'sha256' => hash_file('sha256', $path), 'size' => $size,
                    'verification_status' => config('email_campaigns.scanner') ? 'verified_scanned' : 'verified',
                    'expires_at' => now()->addDays(max(1, config('email_campaigns.retention_days'))),
                ]);
                if ($recipient) {
                    $mapped[$recipient->id] = true;
                }
                if (is_int($source)) {
                    unlink($path);
                }
            }
            if (in_array($campaign->attachment_mode, ['recipient_specific', 'shared_and_recipient_specific'], true)
                && count($mapped) !== $campaign->recipient_count) {
                $this->invalid('Every recipient must have exactly one mapped PDF.');
            }
            if (in_array($campaign->attachment_mode, ['shared', 'shared_and_recipient_specific'], true) && ! $shared) {
                $this->invalid('Shared attachment mode requires at least one shared PDF.');
            }
        } catch (\Throwable $e) {
            Storage::disk('email_documents')->delete($keys);

            throw $e;
        } finally {
            if ($opened) {
                $archive->close();
            }
            if ($temp) {
                foreach (glob($temp.'/*') ?: [] as $path) {
                    unlink($path);
                }
                rmdir($temp);
            }
        }
    }

    public function bytes(EmailCampaignDocument $document): string
    {
        if ($document->purged_at || $document->expires_at->isPast() || ! in_array($document->verification_status, ['verified', 'verified_scanned'], true)
            || (config('email_campaigns.scan_required') && $document->verification_status !== 'verified_scanned')) {
            $this->invalid('An intended document is expired or unverified.');
        }

        try {
            $bytes = Crypt::decryptString(Storage::disk('email_documents')->get($document->storage_key));
        } catch (\Throwable) {
            $this->invalid('An intended document is missing, unreadable or modified.');
        }
        if (strlen($bytes) !== $document->size || ! hash_equals($document->sha256, hash('sha256', $bytes))) {
            $this->invalid('An intended document failed its integrity check.');
        }

        return $bytes;
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['documents' => $message]);
    }
}

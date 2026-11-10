<?php

namespace App\Services\Email;

use App\Jobs\PrepareEmailAudience;
use App\Models\AgentCaptureRecord;
use App\Models\EmailAudienceImport;
use App\Models\EmailCampaign;
use App\Models\EmailContact;
use App\Models\EmailSegment;
use App\Models\LeadHopper;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\XLSX\Reader;

class AudienceService
{
    private const CHUNK = 250;

    public function stage(User $actor, EmailCampaign $campaign, array $input, ?UploadedFile $file = null): EmailAudienceImport
    {
        $this->authorizeCampaign($actor, $campaign);
        $source = $input['source'] ?? '';
        if (! in_array($source, ['manual', 'csv', 'xlsx', 'crm', 'segment'], true)) {
            $this->invalid('Choose manual entry, CSV, XLSX, CRM records or a saved segment.');
        }
        $asserted = filter_var($input['assert_permission'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($asserted && (blank($input['permission_source'] ?? null) || blank($input['permission_at'] ?? null))) {
            $this->invalid('A permission assertion requires its source and permission date.');
        }
        if ($asserted && (! is_string($input['permission_source']) || mb_strlen($input['permission_source']) > 500)) {
            $this->invalid('Permission source must be no more than 500 characters.');
        }

        try {
            $permissionAt = $asserted ? Carbon::parse($input['permission_at']) : null;
        } catch (\Throwable) {
            $this->invalid('Enter a valid permission date.');
        }
        if ($permissionAt?->isFuture()) {
            $this->invalid('Permission cannot be dated in the future.');
        }
        $id = (string) Str::uuid();
        $path = null;
        $duplicatePolicy = $input['duplicate_policy'] ?? 'exclude';
        if (! in_array($duplicatePolicy, ['exclude', 'reject'], true)) {
            $this->invalid('Choose whether duplicates are excluded or reject the import.');
        }
        $payload = ['assert_permission' => $asserted, 'permission_source' => $asserted ? trim($input['permission_source']) : null, 'permission_at' => $permissionAt?->toIso8601String(), 'duplicate_policy' => $duplicatePolicy];
        if ($source === 'xlsx') {
            $worksheet = filter_var($input['worksheet'] ?? 1, FILTER_VALIDATE_INT);
            if (! $worksheet || $worksheet < 1 || $worksheet > 10) {
                $this->invalid('Select a worksheet number between 1 and 10.');
            }
            $payload['worksheet'] = $worksheet;
        }
        if (in_array($source, ['manual', 'csv', 'xlsx'], true)) {
            $path = 'email-audiences/'.$id.'/source.'.($source === 'xlsx' ? 'xlsx' : 'csv');
            if ($source === 'manual') {
                $manual = $input['manual_recipients'] ?? '';
                if (! is_string($manual) || trim($manual) === '' || strlen($manual) > 262144) {
                    $this->invalid('Enter recipients as email,name lines, up to 256 KiB.');
                }
                Storage::disk('local')->put($path, "email,name\n".$manual);
            } else {
                if (! $file || $file->getSize() > ($source === 'xlsx' ? 10485760 : 5242880)
                    || ! in_array(strtolower($file->getClientOriginalExtension()), $source === 'xlsx' ? ['xlsx'] : ['csv', 'txt'], true)) {
                    $this->invalid('Choose a CSV up to 5 MiB or an XLSX up to 10 MiB.');
                }
                $file->storeAs(dirname($path), basename($path), 'local');
            }
        } elseif ($source === 'crm') {
            foreach (['crm_model', 'campaign_code', 'email_field'] as $key) {
                if (! is_string($input[$key] ?? null) || ! preg_match('/^[a-zA-Z0-9_\-]{1,100}$/', $input[$key])) {
                    $this->invalid('Select an authorized CRM source campaign and a named email field.');
                }
            }
            if (! in_array($input['crm_model'], ['hopper', 'capture'], true)) {
                $this->invalid('Unsupported CRM source.');
            }
            if (! empty($input['name_field']) && ! preg_match('/^[a-zA-Z0-9_\-]{1,100}$/', $input['name_field'])) {
                $this->invalid('The name field must be a plain field name.');
            }
            $payload += array_intersect_key($input, array_flip(['crm_model', 'campaign_code', 'email_field', 'name_field']));
            $payload['max_id'] = $this->crmQuery($actor, $payload)->max('id') ?? 0;
        } else {
            $segment = EmailSegment::findOrFail($input['segment_id'] ?? 0);
            abort_unless($actor->isSuperAdmin() || (int) $segment->user_id === (int) $actor->id, 404);
            $payload += ['segment_id' => $segment->id, 'segment_owner' => $segment->user_id, 'filters' => $segment->filters, 'max_id' => $this->segmentQuery($segment->user_id, $segment->filters)->max('id') ?? 0];
        }

        try {
            return DB::transaction(function () use ($actor, $campaign, $source, $id, $path, $payload): EmailAudienceImport {
                $campaign = EmailCampaign::lockForUpdate()->findOrFail($campaign->id);
                $this->authorizeCampaign($actor, $campaign);
                if (EmailAudienceImport::where('email_campaign_id', $campaign->id)->whereIn('status', ['queued', 'processing', 'applying'])->exists()) {
                    $this->invalid('This draft already has an active audience preparation.');
                }
                $run = EmailAudienceImport::create(['id' => $id, 'user_id' => $actor->id, 'email_campaign_id' => $campaign->id, 'source' => $source, 'storage_path' => $path, 'payload' => $payload, 'expires_at' => now()->addDay()]);
                PrepareEmailAudience::dispatch($run->id)->afterCommit();
                activity('email-campaign')->causedBy($actor)->withProperties(['campaign_id' => $campaign->id, 'import_id' => $id, 'source' => $source])->log('email_campaign.audience_staged');

                return $run->refresh();
            });
        } catch (\Throwable $error) {
            // afterCommit queue insertion may fail after the run itself committed.
            // Retain the private source and expose a resumable failed run in history.
            if (EmailAudienceImport::whereKey($id)->exists()) {
                $this->fail($id, 'The queue could not accept this preparation. Restore the email worker connection and resume this run.');
                $this->invalid('The audience was saved but could not be queued. Resume it from Recent preparations after restoring the queue.');
            }
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $error;
        }
    }

    /** Process one bounded slice. True requests another slice, never a send. */
    public function process(string $id): bool
    {
        try {
            return DB::transaction(function () use ($id): bool {
                $run = EmailAudienceImport::lockForUpdate()->find($id);
                if (! $run || ! in_array($run->status, ['queued', 'processing', 'applying'], true)) {
                    return false;
                }
                if ($run->expires_at->isPast()) {
                    $run->update(['status' => 'expired', 'message' => 'This audience preparation expired. Upload or select the source again.']);
                    $this->deleteSource($run);

                    return false;
                }
                $actor = User::find($run->user_id);
                $campaign = EmailCampaign::lockForUpdate()->find($run->email_campaign_id);
                if (! $actor || ! $campaign || ! $this->canManage($actor, $campaign) || $campaign->status !== 'draft') {
                    $run->update(['status' => 'failed', 'message' => 'The draft or submitting account is no longer eligible for audience preparation.']);

                    return false;
                }
                $run->heartbeat_at = now();
                if ($run->status === 'applying') {
                    return $this->applySlice($run, $campaign, $actor);
                }
                $run->status = 'processing';
                [$rows, $cursor, $done, $payload] = $this->readSlice($run, $actor);
                foreach ($rows as [$number, $row]) {
                    $run->processed++;
                    if ($run->processed > min(20000, (int) config('email_campaigns.max_recipients', 10000)) || (in_array($run->source, ['manual', 'csv', 'xlsx'], true) && $number > 50000)) {
                        $this->invalid('The configured recipient or physical-row limit was exceeded.');
                    }
                    $this->stageRow($run, $actor, $number, $row);
                }
                $run->cursor = $cursor;
                $run->payload = $payload;
                if ($done) {
                    $run->status = $run->rejected > 0 ? 'failed' : 'ready';
                    $run->message = $run->rejected > 0 ? 'Correct rejected rows or missing permission evidence and prepare the audience again.' : 'Audience preview is ready. Review counts before adding recipients to the draft.';
                }
                $run->save();

                return ! $done;
            }, 3);
        } catch (ValidationException $error) {
            $this->fail($id, collect($error->errors())->flatten()->first() ?: 'Audience validation failed.');

            return false;
        }
    }

    public function apply(User $actor, EmailAudienceImport $run): EmailCampaign
    {
        return DB::transaction(function () use ($actor, $run): EmailCampaign {
            $run = EmailAudienceImport::lockForUpdate()->findOrFail($run->id);
            $this->authorizeImport($actor, $run);
            $campaign = EmailCampaign::lockForUpdate()->findOrFail($run->email_campaign_id);
            $this->authorizeCampaign($actor, $campaign);
            if ($run->status !== 'ready' || $run->accepted < 1 || $run->expires_at->isPast() || $campaign->recipients()->exists()) {
                $this->invalid('Only a ready eligible audience can be added to an empty draft.');
            }
            $campaign->forceFill(['management_version' => 3, 'audience_status' => 'preparing', 'dry_run_hash' => null])->save();
            $run->update(['status' => 'applying', 'phase' => 'apply', 'cursor' => 0, 'applied' => 0, 'message' => null]);
            PrepareEmailAudience::dispatch($run->id)->afterCommit();
            activity('email-campaign')->causedBy($actor)->withProperties(['campaign_id' => $campaign->id, 'import_id' => $run->id])->log('email_campaign.audience_apply');

            return $campaign;
        });
    }

    public function cancel(User $actor, EmailAudienceImport $run): EmailAudienceImport
    {
        DB::transaction(function () use ($actor, $run): void {
            $locked = EmailAudienceImport::lockForUpdate()->findOrFail($run->id);
            $this->authorizeImport($actor, $locked);
            if ($locked->status === 'applied') {
                $this->invalid('The audience is already part of the draft. Cancel the draft instead.');
            }
            $wasApplying = $locked->status === 'applying';
            $locked->update(['status' => 'cancelled', 'message' => $wasApplying ? 'Preparation cancelled. This partially populated draft remains blocked; create a replacement draft.' : 'Audience preparation cancelled.']);
            if ($wasApplying) {
                EmailCampaign::whereKey($locked->email_campaign_id)->where('status', 'draft')->update(['audience_status' => 'cancelled']);
            }
            $this->deleteSource($locked);
        });

        return $run->refresh();
    }

    public function resume(User $actor, EmailAudienceImport $run): EmailAudienceImport
    {
        return DB::transaction(function () use ($actor, $run): EmailAudienceImport {
            $run = EmailAudienceImport::lockForUpdate()->findOrFail($run->id);
            $this->authorizeImport($actor, $run);
            if ($run->status !== 'failed' || $run->rejected > 0 || $run->expires_at->isPast()) {
                $this->invalid('Correct validation errors in a replacement import. Only interrupted valid preparations can resume.');
            }
            $run->update(['status' => $run->phase === 'apply' ? 'applying' : 'queued', 'message' => null]);
            PrepareEmailAudience::dispatch($run->id)->afterCommit();

            return $run;
        });
    }

    public function fail(string $id, string $message): void
    {
        EmailAudienceImport::whereKey($id)->whereIn('status', ['queued', 'processing', 'applying'])->update(['status' => 'failed', 'message' => mb_substr($message, 0, 500)]);
    }

    public function present(EmailAudienceImport $run): array
    {
        return [...$run->only(['id', 'email_campaign_id', 'source', 'status', 'processed', 'accepted', 'duplicates', 'rejected', 'suppressed', 'applied', 'message']), 'expires_at' => $run->expires_at?->toIso8601String(), 'errors' => DB::table('email_audience_rows')->where('import_id', $run->id)->whereNotNull('error_code')->orderBy('record_number')->limit(25)->get(['record_number', 'error_code'])->all()];
    }

    public function authorizeImport(User $actor, EmailAudienceImport $run): void
    {
        abort_unless($actor->isSuperAdmin() || ($actor->isAdmin() && (int) $run->user_id === (int) $actor->id), 404);
    }

    public function contacts(User $actor, array $filters = []): Builder
    {
        abort_unless($actor->isAdmin(), 403);
        $query = EmailContact::query()->when(! $actor->isSuperAdmin(), fn ($q) => $q->where('user_id', $actor->id));
        if (! empty($filters['q'])) {
            $query->where(fn ($q) => $q->where('email', 'like', '%'.mb_substr($filters['q'], 0, 150).'%')->orWhere('name', 'like', '%'.mb_substr($filters['q'], 0, 150).'%'));
        }
        if (! empty($filters['consent_status'])) {
            $query->where('consent_status', $filters['consent_status']);
        }

        return $query;
    }

    public function saveSegment(User $actor, array $input): EmailSegment
    {
        abort_unless($actor->isAdmin(), 403);
        $filters = $input['filters'] ?? [];
        if (array_diff(array_keys($filters), ['tag', 'campaign_code', 'source', 'consent_status'])) {
            $this->invalid('Segments support tag, source campaign, source and consent state filters only.');
        }
        foreach ($filters as $value) {
            if ($value !== null && (! is_string($value) || strlen($value) > 100)) {
                $this->invalid('Segment filter values must be short text.');
            }
        }

        return EmailSegment::create(['user_id' => $actor->id, 'name' => $input['name'], 'filters' => array_filter($filters, fn ($v) => $v !== '' && $v !== null)]);
    }

    public function cleanupExpired(): int
    {
        $count = 0;
        foreach (EmailAudienceImport::where('expires_at', '<=', now())->whereNotIn('status', ['expired', 'applying'])->lazyById(100, 'id') as $run) {
            $this->deleteSource($run);
            DB::table('email_audience_rows')->where('import_id', $run->id)->delete();
            $run->update(['status' => 'expired', 'payload' => [], 'message' => 'Audience staging data expired.']);
            $count++;
        }

        return $count;
    }

    private function readSlice(EmailAudienceImport $run, User $actor): array
    {
        $payload = $run->payload;
        if (in_array($run->source, ['crm', 'segment'], true)) {
            $query = $run->source === 'crm' ? $this->crmQuery($actor, $payload) : $this->segmentQuery($payload['segment_owner'], $payload['filters']);
            $records = $query->where('id', '>', $run->cursor)->where('id', '<=', $payload['max_id'])->orderBy('id')->limit(self::CHUNK)->get();
            $rows = [];
            foreach ($records as $record) {
                if ($run->source === 'segment') {
                    $row = $record->only(['email', 'name', 'employee_id', 'period']);
                } else {
                    $data = $record->{$payload['crm_model'] === 'hopper' ? 'custom_data' : 'capture_data'} ?? [];
                    $row = ['email' => $data[$payload['email_field']] ?? '', 'name' => ! empty($payload['name_field']) ? ($data[$payload['name_field']] ?? '') : ($record->client_name ?? '')];
                }
                $rows[] = [(int) $record->id, $row];
            }

            return [$rows, $records->last()?->id ?? $run->cursor, $records->count() < self::CHUNK, $payload];
        }
        $path = Storage::disk('local')->path($run->storage_path);
        if (! is_file($path)) {
            $this->invalid('The private source is unavailable. Prepare the audience again.');
        }
        if ($run->source === 'xlsx') {
            return $this->readXlsx($run, $path, $payload);
        }
        $stream = fopen($path, 'rb');
        if (! $stream) {
            $this->invalid('The private source could not be read.');
        }

        try {
            if ($run->cursor === 0) {
                $payload['header'] = $this->header(fgetcsv($stream, 0, ',', '"', '') ?: []);
                $run->record_number = 1;
            } else {
                fseek($stream, $run->cursor);
            }
            $rows = [];
            while (count($rows) < self::CHUNK && ($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $number = ++$run->record_number;
                if ($number > 50000) {
                    $this->invalid('The physical-row limit was exceeded.');
                }
                if ($values === [null]) {
                    continue;
                }
                if ($run->source === 'manual' && count($values) === 1) {
                    $values[] = '';
                }
                $row = count($values) === count($payload['header']) ? array_combine($payload['header'], $values) : ['_error' => 'malformed_row'];
                $rows[] = [$number, $row];
            }

            return [$rows, ftell($stream), feof($stream), $payload];
        } finally {
            fclose($stream);
        }
    }

    private function readXlsx(EmailAudienceImport $run, string $path, array $payload): array
    {
        if ($run->cursor === 0) {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true || $zip->numFiles > 250) {
                $this->invalid('The spreadsheet archive is invalid or has too many entries.');
            }

            try {
                $total = 0;
                $sheets = 0;
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entry = $zip->statIndex($index);
                    $total += $entry['size'];
                    $sheets += preg_match('#^xl/worksheets/[^/]+\.xml$#', $entry['name']);
                    if ($total > 104857600 || $entry['size'] > 52428800 || $entry['size'] / max(1, $entry['comp_size']) > 200 || str_contains(strtolower($entry['name']), 'vbaproject') || ! empty($entry['encryption_method']) || $sheets > 10) {
                        $this->invalid('Spreadsheet expansion or macro policy rejected this file.');
                    }
                }
            } finally {
                $zip->close();
            }
        }
        $reader = new Reader;
        $reader->open($path);

        try {
            $rows = [];
            $last = (int) $run->cursor;
            $done = true;
            $found = false;
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() + 1 !== (int) ($payload['worksheet'] ?? 1)) {
                    continue;
                }
                $found = true;
                foreach ($sheet->getRowIterator() as $number => $xlsxRow) {
                    if ($number === 1) {
                        $payload['header'] = $this->header($xlsxRow->toArray());

                        continue;
                    }
                    if ($number <= $run->cursor) {
                        continue;
                    }
                    if (count($rows) >= self::CHUNK) {
                        $done = false;

                        break;
                    }
                    $values = array_pad($xlsxRow->toArray(), count($payload['header']), '');
                    $hasFormula = collect($xlsxRow->getCells())->contains(fn ($cell) => $cell instanceof \OpenSpout\Common\Entity\Cell\FormulaCell);
                    $row = ! $hasFormula && count($values) === count($payload['header']) ? array_combine($payload['header'], $values) : ['_error' => $hasFormula ? 'formula_not_allowed' : 'malformed_row'];
                    $rows[] = [$number, $row];
                    $last = $number;
                }

                break;
            }
            if (! $found) {
                $this->invalid('The selected worksheet does not exist in this workbook.');
            }

            return [$rows, $last, $done, $payload];
        } finally {
            $reader->close();
        }
    }

    private function stageRow(EmailAudienceImport $run, User $actor, int $number, array $row): void
    {
        $error = $row['_error'] ?? null;
        foreach ($row as $value) {
            if (($value !== null && ! is_scalar($value)) || ! mb_check_encoding((string) $value, 'UTF-8') || strlen((string) $value) > 255 || preg_match('/[\x00-\x1f\x7f]|^[=+@-]/u', trim((string) $value))) {
                $error = 'unsafe_or_oversized_value';
            }
        }
        $email = is_string($row['email'] ?? null) ? strtolower(trim($row['email'])) : '';
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error ??= 'invalid_email';
        }
        if (! $error && DB::table('email_audience_rows')->where('import_id', $run->id)->where('email', $email)->exists()) {
            $run->duplicates++;
            if (($run->payload['duplicate_policy'] ?? 'exclude') === 'reject') {
                DB::table('email_audience_rows')->insert(['import_id' => $run->id, 'record_number' => $number, 'error_code' => 'duplicate_email']);
                $run->rejected++;
            }

            return;
        }
        $payload = $run->payload;
        $contactOwner = $run->source === 'segment' ? $payload['segment_owner'] : $actor->id;
        $contact = ! $error ? EmailContact::where('user_id', $contactOwner)->where('email', $email)->first() : null;
        $consent = ['consent_status' => 'unknown', 'consent_source' => null, 'consent_asserted_at' => null];
        if ($contact && in_array($contact->consent_status, ['opted_in', 'asserted'], true) && $contact->consent_source && $contact->consent_asserted_at) {
            $consent = $contact->only(array_keys($consent));
        } elseif (($payload['assert_permission'] ?? false) && $contact?->consent_status !== 'withdrawn') {
            $consent = ['consent_status' => 'asserted', 'consent_source' => $payload['permission_source'], 'consent_asserted_at' => Carbon::parse($payload['permission_at'])];
        }
        if (! $error && app(SuppressionService::class)->blocked($email, 'marketing')) {
            $run->suppressed++;

            return;
        }
        if ($consent['consent_status'] === 'unknown') {
            $error ??= 'documented_permission_required';
        }
        DB::table('email_audience_rows')->insert(['import_id' => $run->id, 'record_number' => $number, 'email' => $error ? null : $email, 'name' => $error ? null : (trim((string) ($row['name'] ?? '')) ?: null), 'employee_id' => $error ? null : ($row['employee_id'] ?? null), 'period' => $error ? null : ($row['period'] ?? null), ...$consent, 'error_code' => $error]);
        if ($error) {
            $run->rejected++;
        } else {
            $run->accepted++;
        }
    }

    private function applySlice(EmailAudienceImport $run, EmailCampaign $campaign, User $actor): bool
    {
        $rows = DB::table('email_audience_rows')->where('import_id', $run->id)->whereNull('error_code')->where('id', '>', $run->cursor)->orderBy('id')->limit(self::CHUNK)->get();
        foreach ($rows as $row) {
            if (app(SuppressionService::class)->blocked($row->email, 'marketing')) {
                $run->suppressed++;
                $run->cursor = $row->id;

                continue;
            }
            $contact = EmailContact::firstOrNew(['user_id' => $actor->id, 'email' => $row->email]);
            if ($contact->exists && $contact->consent_status === 'withdrawn') {
                $run->rejected++;
                $run->cursor = $row->id;

                continue;
            }
            $contact->fill(['name' => $row->name, 'employee_id' => $row->employee_id, 'period' => $row->period, 'source' => $run->source, 'campaign_code' => $run->payload['campaign_code'] ?? null, 'consent_status' => $row->consent_status, 'consent_source' => $row->consent_source, 'consent_asserted_at' => $row->consent_asserted_at, 'consent_actor_id' => $actor->id])->save();
            $created = DB::table('email_campaign_recipients')->insertOrIgnore(['email_campaign_id' => $campaign->id, 'email' => $row->email, 'name' => $row->name, 'employee_id' => $row->employee_id, 'period' => $row->period, 'status' => 'pending', 'email_contact_id' => $contact->id, 'consent_status' => $row->consent_status, 'consent_source' => $row->consent_source, 'consent_asserted_at' => $row->consent_asserted_at, 'audience_source' => $run->source, 'created_at' => now(), 'updated_at' => now()]);
            $run->applied += $created;
            $run->cursor = $row->id;
        }
        $more = $rows->count() === self::CHUNK;
        if (! $more) {
            $count = $campaign->recipients()->count();
            $campaign->forceFill(['recipient_count' => $count, 'open_count' => $count, 'attention_count' => 0, 'audience_status' => $count > 0 && $run->rejected === 0 ? 'ready' : 'failed'])->save();
            $run->status = 'applied';
            $run->message = $count > 0 ? 'Eligible recipients were added to the draft. Review before approval.' : 'No recipients remain eligible. Prepare a replacement audience.';
            $this->deleteSource($run);
        }
        $run->save();

        return $more;
    }

    private function header(array $values): array
    {
        $header = array_map(fn ($value) => strtolower(trim((string) $value, "\xEF\xBB\xBF \t\r\n")), $values);
        if (! in_array('email', $header, true) || count($header) !== count(array_unique($header)) || array_diff($header, ['email', 'name', 'employee_id', 'period'])) {
            $this->invalid('Use unique email,name,employee_id,period headers; only email is required.');
        }

        return $header;
    }

    private function crmQuery(User $actor, array $payload): Builder
    {
        $query = $payload['crm_model'] === 'hopper' ? LeadHopper::query() : AgentCaptureRecord::query();
        $query->where('campaign_code', $payload['campaign_code']);
        if (! $actor->isSuperAdmin()) {
            $query->where($payload['crm_model'] === 'hopper' ? 'assigned_to_user_id' : 'user_id', $actor->id);
        }

        return $query;
    }

    private function segmentQuery(int $owner, array $filters): Builder
    {
        $query = EmailContact::where('user_id', $owner);
        foreach (['campaign_code', 'source', 'consent_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['tag'])) {
            $query->whereJsonContains('tags', $filters['tag']);
        }

        return $query;
    }

    private function authorizeCampaign(User $actor, EmailCampaign $campaign): void
    {
        abort_unless($this->canManage($actor, $campaign), 403);
        abort_if(in_array($campaign->approval_state, ['queued', 'processing'], true), 409, 'Approval preparation is in progress. Cancel the campaign to stop it.');
        abort_unless($campaign->status === 'draft' && $campaign->purpose === 'marketing' && $campaign->attachment_mode === 'none' && ! $campaign->template?->pdf_enabled, 409, 'Audience preparation requires an attachment-free marketing draft.');
    }

    private function canManage(User $actor, EmailCampaign $campaign): bool
    {
        return $actor->isSuperAdmin() || ($actor->isAdmin() && (int) $campaign->created_by_user_id === (int) $actor->id && $campaign->purpose === 'marketing' && $campaign->attachment_mode === 'none' && ! $campaign->template?->pdf_enabled);
    }

    private function deleteSource(EmailAudienceImport $run): void
    {
        if ($run->storage_path) {
            Storage::disk('local')->delete($run->storage_path);
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['audience' => $message]);
    }
}

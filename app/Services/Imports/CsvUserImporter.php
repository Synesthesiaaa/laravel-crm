<?php

namespace App\Services\Imports;

use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\User;
use App\Services\UserService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

final class CsvUserImporter
{
    private array $counts = ['processed' => 0, 'created' => 0, 'skipped' => 0, 'rejected' => 0];

    private array $position = ['offset' => 0, 'record' => 1, 'complete' => false];

    public function __construct(private UserService $users) {}

    public function counts(): array
    {
        return $this->counts;
    }

    public function position(): array
    {
        return $this->position;
    }

    public function validateHeader(string $path): void
    {
        $stream = fopen($path, 'rb');

        try {
            $this->headers($stream);
        } finally {
            fclose($stream);
        }
    }

    /** Callbacks receive static diagnostics only, never source values or exceptions. */
    public function import(string $path, int $chunk, bool $dryRun, bool $allowPrivileged, Closure $onError, Closure $onProgress, ?UserImportBatch $batchContext = null): array
    {
        $this->counts = array_fill_keys(array_keys($this->counts), 0);
        $this->position = ['offset' => 0, 'record' => 1, 'complete' => false];
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidUserImportInput('Input must be a readable local CSV file.');
        }

        $stream = fopen($path, 'rb');
        if ($stream === false) {
            throw new InvalidUserImportInput('Cannot open the input CSV file.');
        }
        $index = null;

        try {
            $headers = $this->headers($stream);
            $index = $batchContext?->identities ?? new UserImportIdentityIndex;
            if ($batchContext !== null && $batchContext->offset > 0 && fseek($stream, $batchContext->offset) !== 0) {
                throw new RuntimeException('Cannot resume the uploaded CSV.');
            }
            DB::connection()->disableQueryLog();
            $rules = (new StoreUserRequest)->rules();
            $rules['username'] = ['required', 'string', 'max:80'];
            $rules['password'] = array_values(array_diff($rules['password'], ['confirmed']));
            // Bcrypt truncates after 72 bytes. Never silently import a truncated password.
            $rules['password'][] = function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && strlen($value) > (config('hashing.driver', 'bcrypt') === 'bcrypt' ? 72 : 4096)) {
                    $fail('Password exceeds the configured hashing limit.');
                }
            };
            $rules['email'] = ['required', 'email:rfc', 'max:255'];
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $batch = [];
            $record = $batchContext?->record ?? 1;
            $consumed = 0;

            while (($batchContext === null || $consumed < $batchContext->limit)
                && ($row = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $record++;
                $consumed++;
                if ($row === [null]) {
                    continue;
                }
                $this->counts['processed']++;
                if (count($row) !== count($headers)) {
                    $this->reject($record, 'Column count does not match the header.', $onError);

                    continue;
                }
                if (! mb_check_encoding($row, 'UTF-8') || str_contains(implode('', $row), "\0")) {
                    $this->reject($record, 'Row must contain valid UTF-8 text without null bytes.', $onError);

                    continue;
                }
                $data = $this->normalize(array_combine($headers, $row));
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    $errors = [];
                    foreach ($validator->failed() as $field => $failures) {
                        // Do not echo input values, even for attacker-controlled roles or secrets.
                        $errors[] = $field.': '.implode(', ', array_keys($failures));
                    }
                    $this->reject($record, implode('; ', $errors), $onError);

                    continue;
                }
                if (! $allowPrivileged && $data['role'] !== User::ROLE_AGENT) {
                    $this->reject($record, 'Non-Agent roles require --allow-privileged-roles.', $onError);

                    continue;
                }
                $batch[] = ['record' => $record, 'data' => $validator->validated()];
                if (count($batch) >= $chunk) {
                    $this->processBatch($batch, $index, $dryRun, $onError);
                    $batch = [];
                    $onProgress($this->counts);
                }
            }
            if ($batchContext === null && ! feof($stream)) {
                throw new RuntimeException('Cannot finish reading the CSV file.');
            }
            if ($batch !== []) {
                $this->processBatch($batch, $index, $dryRun, $onError);
                $onProgress($this->counts);
            }
            $this->position = ['offset' => ftell($stream), 'record' => $record, 'complete' => feof($stream) || ftell($stream) >= fstat($stream)['size']];

            return $this->counts;
        } finally {
            fclose($stream);
            $index?->close();
        }
    }

    private function headers($stream): array
    {
        $headers = fgetcsv($stream, null, ',', '"', '');
        if ($headers === false || $headers === [null]) {
            throw new InvalidUserImportInput('CSV must start with a header row.');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
        $allowed = array_merge(array_keys((new StoreUserRequest)->rules()), ['email', 'name']);
        if (array_diff(['username', 'full_name', 'password'], $headers) !== []) {
            throw new InvalidUserImportInput('Required CSV headers: username,full_name,password.');
        }
        if (count(array_unique($headers)) !== count($headers) || array_diff($headers, $allowed) !== []) {
            throw new InvalidUserImportInput('CSV contains duplicate or unsupported headers. See docs/bulk-user-import.md.');
        }

        return $headers;
    }

    private function normalize(array $data): array
    {
        foreach ($data as $field => $value) {
            if (! in_array($field, ['password', 'vici_pass', 'sip_password'], true)) {
                $data[$field] = trim((string) $value);
            }
        }
        $data['role'] = ($data['role'] ?? '') !== '' ? $data['role'] : User::ROLE_AGENT;
        $host = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'local';
        $data['email'] = ($data['email'] ?? '') !== '' ? $data['email'] : $data['username'].'@'.$host;
        foreach (['auto_vici_login' => false, 'default_blended' => true] as $field => $default) {
            $value = strtolower($data[$field] ?? '');
            $data[$field] = match ($value) {
                '' => $default,
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => $value,
            };
        }
        foreach (['vici_user', 'vici_pass', 'extension', 'sip_password', 'default_campaign', 'default_ingroups'] as $field) {
            if (($data[$field] ?? null) === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function processBatch(array $batch, UserImportIdentityStore $index, bool $dryRun, Closure $onError): void
    {
        $data = array_column($batch, 'data');
        $existing = User::withTrashed()
            ->whereIn('username', array_column($data, 'username'))
            ->orWhereIn('email', array_column($data, 'email'))
            ->get(['username', 'email']);
        $usernames = [];
        $emails = [];
        foreach ($existing as $user) {
            $usernames[mb_strtolower(trim((string) $user->username))] = true;
            $emails[mb_strtolower(trim((string) $user->email))] = true;
        }

        $index->beginBatch();
        foreach ($batch as ['record' => $record, 'data' => $row]) {
            if (isset($usernames[mb_strtolower($row['username'])]) || $index->contains('username', $row['username'])) {
                $this->counts['skipped']++;

                continue;
            }
            if (isset($emails[mb_strtolower($row['email'])]) || $index->contains('email', $row['email'])) {
                $this->reject($record, 'Email is already in use.', $onError);

                continue;
            }

            if (! $dryRun) {
                try {
                    $this->users->create($row, syncVicidial: false);
                } catch (UniqueConstraintViolationException) {
                    // A concurrent importer or database collation may win the unique index race.
                    if (User::withTrashed()->where('username', $row['username'])->exists()) {
                        $this->counts['skipped']++;
                    } else {
                        $this->reject($record, 'Account identity conflicts with an existing account.', $onError);
                    }

                    continue;
                }
            }
            $this->counts['created']++;
            $index->remember($row['username'], $row['email']);
        }
        $index->endBatch();
    }

    private function reject(int $record, string $errors, Closure $onError): void
    {
        $this->counts['rejected']++;
        $onError($record, $errors);
    }
}

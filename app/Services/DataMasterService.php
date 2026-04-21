<?php

namespace App\Services;

use App\Contracts\Repositories\FormFieldRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DataMasterService
{
    public function __construct(
        protected CampaignService $campaignService,
        protected FormFieldRepositoryInterface $formFieldRepository,
    ) {}

    /**
     * Build the default Data Master column layout for a given form.
     *
     * Order: `id` first, then fields defined in `form_fields` ordered by `field_order`,
     * then any remaining DB columns (from the sample row) appended at the end.
     * Headers use `field_label` where available, otherwise a humanized `field_name`.
     *
     * @param  array<int, string>|null  $availableColumns  Column names present in the actual DB row.
     * @return array{columns: list<string>, headers: array<string, string>}
     */
    public function getColumnLayout(
        string $campaignCode,
        string $formType,
        ?array $availableColumns = null,
    ): array {
        $fields = $this->formFieldRepository->getFieldsForForm($campaignCode, $formType);

        $ordered = [];
        $headers = [];
        foreach ($fields as $field) {
            $name = (string) $field->field_name;
            if ($name === '') {
                continue;
            }
            $ordered[] = $name;
            $label = (string) ($field->field_label ?? '');
            $headers[$name] = $label !== '' ? $label : Str::headline($name);
        }

        if ($availableColumns === null) {
            $columns = array_values(array_unique(array_merge(['id'], $ordered)));
        } else {
            $available = array_values(array_unique($availableColumns));
            $orderedInDb = array_values(array_intersect($ordered, $available));
            $idFirst = in_array('id', $available, true) ? ['id'] : [];
            $remaining = array_values(array_diff($available, $idFirst, $orderedInDb));
            $columns = array_values(array_unique(array_merge($idFirst, $orderedInDb, $remaining)));
        }

        foreach ($columns as $col) {
            if (! isset($headers[$col])) {
                $headers[$col] = Str::headline($col);
            }
        }

        return [
            'columns' => $columns,
            'headers' => $headers,
        ];
    }

    /**
     * Returns allowed table names for a given campaign config.
     *
     * @param  array<string, mixed>  $campaignConfig
     * @return list<string>
     */
    public function getAllowedTables(array $campaignConfig): array
    {
        $allowed = [];
        foreach ($campaignConfig['forms'] ?? [] as $formConfig) {
            $t = $formConfig['table_name'] ?? $formConfig['table'] ?? '';
            if ($t !== '') {
                $allowed[] = $t;
            }
        }

        return $allowed;
    }

    public function getRecords(string $tableName, array $allowedTables, int $perPage = 20): LengthAwarePaginator
    {
        if (! $this->isTableAllowed($tableName, $allowedTables)) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        try {
            return DB::table($tableName)->orderByDesc('id')->paginate($perPage);
        } catch (\Throwable) {
            return new LengthAwarePaginator([], 0, $perPage);
        }
    }

    public function getRecord(string $tableName, int $id, array $allowedTables): ?object
    {
        if (! $this->isTableAllowed($tableName, $allowedTables)) {
            return null;
        }

        return DB::table($tableName)->where('id', $id)->first();
    }

    /** @param array<string, mixed> $updates */
    public function updateRecord(string $tableName, int $id, array $updates, array $allowedTables): bool
    {
        if (! $this->isTableAllowed($tableName, $allowedTables)) {
            return false;
        }
        if (empty($updates)) {
            return true;
        }

        return DB::table($tableName)->where('id', $id)->update($updates) >= 0;
    }

    public function deleteRecord(string $tableName, int $id, array $allowedTables): bool
    {
        if (! $this->isTableAllowed($tableName, $allowedTables)) {
            return false;
        }

        return DB::table($tableName)->where('id', $id)->delete() > 0;
    }

    public function isTableAllowed(string $tableName, array $allowedTables): bool
    {
        return in_array($tableName, $allowedTables, true);
    }
}

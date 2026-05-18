<?php

namespace App\Services;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ensures {@see FormField} rows exist for every non-system column on each form storage table,
 * inferring requirement and type from the database (no per-form arrays in seeders).
 */
class FormFieldsSchemaSyncService
{
    /**
     * Filled by the form layout or submission pipeline, not rendered from form_fields.
     */
    private const SYSTEM_FILL = [
        'id',
        'created_at',
        'updated_at',
        'date',
        'request_id',
        'agent',
        'lead_id',
        'phone_number',
    ];

    public function syncAllFromRegisteredForms(): void
    {
        Form::query()->orderBy('campaign_code')->orderBy('display_order')->each(function (Form $form): void {
            $this->syncTable($form->campaign_code, $form->form_code, $form->table_name);
        });
    }

    public function syncTable(string $campaignCode, string $formCode, string $tableName): void
    {
        if ($tableName === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $tableName)) {
            return;
        }
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        $columns = match ($driver) {
            'mysql', 'mariadb' => $this->mysqlColumns($tableName),
            'sqlite' => $this->sqliteColumns($tableName),
            default => [],
        };

        if ($columns === []) {
            return;
        }

        $ordinal = 0;
        foreach ($columns as $col) {
            $name = $col['name'];
            if ($col['skip'] || in_array($name, self::SYSTEM_FILL, true)) {
                continue;
            }

            $existing = FormField::withTrashed()
                ->where('campaign_code', $campaignCode)
                ->where('form_type', $formCode)
                ->where('field_name', $name)
                ->first();

            if ($existing !== null) {
                if ($existing->trashed()) {
                    $existing->restore();
                }

                continue;
            }

            $ordinal++;
            $label = Str::headline(str_replace(['_', '-'], ' ', $name));

            FormField::create([
                'campaign_code' => $campaignCode,
                'form_type' => $formCode,
                'field_name' => $name,
                'field_label' => $label,
                'field_type' => $col['field_type'],
                'is_required' => $col['required'],
                'field_order' => $ordinal * 10,
            ]);
        }
    }

    /**
     * @return list<array{name: string, required: bool, field_type: string, skip: bool}>
     */
    private function mysqlColumns(string $tableName): array
    {
        /** @var list<object> $rows */
        $rows = DB::select('SHOW FULL COLUMNS FROM `'.$tableName.'`');

        return $this->mapDriverRowsFromStdClass($rows, function (object $row): array {
            $extra = strtolower((string) ($row->Extra ?? ''));
            $nullable = strtoupper((string) ($row->Null ?? '')) === 'YES';

            return [
                'field_type' => $this->mapMysqlType((string) ($row->Type ?? '')),
                'required' => ! $nullable,
                'skip' => str_contains($extra, 'auto_increment'),
            ];
        }, 'Field');
    }

    /**
     * @return list<array{name: string, required: bool, field_type: string, skip: bool}>
     */
    private function sqliteColumns(string $tableName): array
    {
        /** @var list<object> $rows */
        $rows = DB::select('PRAGMA table_info('.$tableName.')');

        return $this->mapDriverRowsFromStdClass($rows, function (object $row): array {
            $notNull = ((int) ($row->notnull ?? 0)) === 1;
            $pk = ((int) ($row->pk ?? 0)) === 1;

            return [
                'field_type' => $this->mapSqliteDeclaredType((string) ($row->type ?? 'TEXT')),
                'required' => $notNull,
                'skip' => $pk,
            ];
        }, 'name');
    }

    /**
     * @param  list<object>  $rows
     * @param  callable(object): array{field_type: string, required: bool, skip: bool}  $map
     * @return list<array{name: string, required: bool, field_type: string, skip: bool}>
     */
    private function mapDriverRowsFromStdClass(array $rows, callable $map, string $nameKey): array
    {
        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row->{$nameKey} ?? '');
            if ($name === '') {
                continue;
            }
            $m = $map($row);
            $out[] = [
                'name' => $name,
                'required' => $m['required'],
                'field_type' => $m['field_type'],
                'skip' => $m['skip'],
            ];
        }

        return $out;
    }

    private function mapMysqlType(string $sqlType): string
    {
        $t = strtolower($sqlType);
        if (preg_match('/\b(decimal|float|double|numeric|real|int|bigint|smallint|mediumint|tinyint)\b/', $t)) {
            return 'number';
        }
        if (str_contains($t, 'date') || str_contains($t, 'time')) {
            return 'date';
        }
        if (str_contains($t, 'text') || str_contains($t, 'blob') || str_contains($t, 'json')) {
            return 'textarea';
        }

        return 'text';
    }

    private function mapSqliteDeclaredType(string $declared): string
    {
        $t = strtoupper(trim($declared));
        if ($t === '' || str_contains($t, 'CHAR') || $t === 'TEXT' || str_contains($t, 'CLOB')) {
            return 'text';
        }
        if (str_contains($t, 'INT') || str_contains($t, 'REAL') || str_contains($t, 'FLOA') || str_contains($t, 'DOUB') || str_contains($t, 'NUM')) {
            return 'number';
        }
        if (str_contains($t, 'BLOB')) {
            return 'textarea';
        }

        return 'text';
    }
}

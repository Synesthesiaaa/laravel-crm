<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns populated by the submission pipeline or framework internals.
     *
     * @var list<string>
     */
    private const SYSTEM_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'date',
        'request_id',
        'agent',
        'lead_id',
        'phone_number',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('forms') || ! Schema::hasTable('form_fields')) {
            return;
        }

        $fieldNamesByTable = [];
        $forms = DB::table('forms')
            ->where('is_active', true)
            ->get(['campaign_code', 'form_code', 'table_name']);

        foreach ($forms as $form) {
            $tableName = (string) $form->table_name;
            if ($tableName === ''
                || ! preg_match('/^[A-Za-z0-9_]+$/', $tableName)
                || ! Schema::hasTable($tableName)) {
                continue;
            }

            $fieldQuery = DB::table('form_fields')
                ->where('campaign_code', $form->campaign_code)
                ->where('form_type', $form->form_code);

            if (Schema::hasColumn('form_fields', 'deleted_at')) {
                $fieldQuery->whereNull('deleted_at');
            }

            $fieldNamesByTable[$tableName] = array_values(array_unique(array_merge(
                $fieldNamesByTable[$tableName] ?? [],
                $fieldQuery
                    ->pluck('field_name')
                    ->filter(fn (mixed $fieldName): bool => is_string($fieldName) && $fieldName !== '')
                    ->all(),
            )));
        }

        foreach ($fieldNamesByTable as $tableName => $fieldNames) {
            $registeredFields = array_fill_keys($fieldNames, true);

            foreach (Schema::getColumns($tableName) as $column) {
                $columnName = (string) ($column['name'] ?? '');
                if ($columnName === ''
                    || isset($registeredFields[$columnName])
                    || in_array($columnName, self::SYSTEM_COLUMNS, true)
                    || ($column['nullable'] ?? false)
                    || ($column['default'] ?? null) !== null
                    || ($column['auto_increment'] ?? false)
                    || ($column['generation'] ?? null) !== null) {
                    continue;
                }

                $this->makeColumnNullable($tableName, $column);
            }
        }
    }

    /**
     * Nullability cannot be safely reversed after rows may have been saved without the legacy field.
     */
    public function down(): void {}

    /**
     * @param  array<string, mixed>  $column
     */
    private function makeColumnNullable(string $tableName, array $column): void
    {
        $columnName = (string) ($column['name'] ?? '');
        $columnType = trim((string) ($column['type'] ?? ''));
        if ($columnName === '' || $columnType === '') {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            $typeName = strtolower((string) ($column['type_name'] ?? 'string'));
            Schema::table($tableName, function (Blueprint $table) use ($columnName, $typeName): void {
                if ($typeName === 'text') {
                    $table->text($columnName)->nullable()->change();

                    return;
                }

                $table->string($columnName)->nullable()->change();
            });

            return;
        }

        $definition = $columnType;
        $collation = (string) ($column['collation'] ?? '');
        if ($collation !== '' && preg_match('/^[A-Za-z0-9_]+$/', $collation)) {
            $charset = explode('_', $collation, 2)[0];
            if (preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
                $definition .= " CHARACTER SET {$charset} COLLATE {$collation}";
            }
        }

        $definition .= ' NULL';

        $comment = $column['comment'] ?? null;
        if (is_string($comment) && $comment !== '') {
            $definition .= ' COMMENT '.DB::getPdo()->quote($comment);
        }

        DB::statement(sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s %s',
            $this->quoteIdentifier($tableName),
            $this->quoteIdentifier($columnName),
            $definition,
        ));
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }
};

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
        'date',
        'request_id',
        'agent',
        'created_at',
        'updated_at',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('forms') || ! Schema::hasTable('form_fields')) {
            return;
        }

        $formQuery = DB::table('forms');
        if (Schema::hasColumn('forms', 'deleted_at')) {
            $formQuery->whereNull('deleted_at');
        }

        $registeredFieldsByTable = [];
        foreach ($formQuery->get(['campaign_code', 'form_code', 'table_name']) as $form) {
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

            $fieldNames = $fieldQuery
                ->pluck('field_name')
                ->filter(fn (mixed $fieldName): bool => is_string($fieldName) && $fieldName !== '')
                ->all();
            if ($fieldNames === []) {
                continue;
            }

            $registeredFieldsByTable[$tableName] = array_values(array_unique(array_merge(
                $registeredFieldsByTable[$tableName] ?? [],
                $fieldNames,
            )));
        }

        foreach ($registeredFieldsByTable as $tableName => $fieldNames) {
            $columnsToDrop = array_values(array_diff(
                Schema::getColumnListing($tableName),
                array_merge(self::SYSTEM_COLUMNS, $fieldNames),
            ));
            if ($columnsToDrop === []) {
                continue;
            }

            $this->dropIndexesContainingColumns($tableName, $columnsToDrop);
            Schema::table($tableName, function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Dropped columns cannot be safely reconstructed or restored automatically.
     */
    public function down(): void {}

    /**
     * @param  list<string>  $columnsToDrop
     */
    private function dropIndexesContainingColumns(string $tableName, array $columnsToDrop): void
    {
        $indexesToDrop = [];
        foreach (Schema::getIndexes($tableName) as $index) {
            if (($index['primary'] ?? false)
                || array_intersect($index['columns'] ?? [], $columnsToDrop) === []) {
                continue;
            }

            $indexesToDrop[] = $index['name'];
        }

        if ($indexesToDrop === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexesToDrop): void {
            foreach ($indexesToDrop as $indexName) {
                $table->dropIndex($indexName);
            }
        });
    }
};

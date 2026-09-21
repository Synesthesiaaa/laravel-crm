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

        $processedTables = [];
        $forms = DB::table('forms')
            ->where('is_active', true)
            ->get(['campaign_code', 'form_code', 'table_name']);

        foreach ($forms as $form) {
            $tableName = (string) $form->table_name;
            if ($tableName === ''
                || isset($processedTables[$tableName])
                || ! preg_match('/^[A-Za-z0-9_]+$/', $tableName)
                || ! Schema::hasTable($tableName)) {
                continue;
            }

            $processedTables[$tableName] = true;
            $fieldQuery = DB::table('form_fields')
                ->where('campaign_code', $form->campaign_code)
                ->where('form_type', $form->form_code);

            if (Schema::hasColumn('form_fields', 'deleted_at')) {
                $fieldQuery->whereNull('deleted_at');
            }

            $fieldNames = $fieldQuery
                ->pluck('field_name')
                ->filter(fn (mixed $fieldName): bool => is_string($fieldName) && $fieldName !== '')
                ->unique()
                ->values()
                ->all();
            $storageColumns = Schema::getColumnListing($tableName);
            $storageFieldNames = array_values(array_diff($storageColumns, self::SYSTEM_COLUMNS));
            $unmatchedFields = array_values(array_diff($fieldNames, $storageColumns));
            $unrepresentedColumns = array_values(array_diff($storageFieldNames, $fieldNames));

            if (count($unmatchedFields) !== 1 || count($unrepresentedColumns) !== 1) {
                continue;
            }

            $sourceColumn = $unrepresentedColumns[0];
            $targetColumn = $unmatchedFields[0];
            Schema::table($tableName, function (Blueprint $table) use ($sourceColumn, $targetColumn): void {
                $table->renameColumn($sourceColumn, $targetColumn);
            });
        }
    }

    /**
     * The original column names are not stored, so an automatic reversal is unsafe.
     */
    public function down(): void {}
};

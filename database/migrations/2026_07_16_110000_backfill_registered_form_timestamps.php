<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('forms')) {
            return;
        }

        $tableNames = DB::table('forms')
            ->whereNotNull('table_name')
            ->pluck('table_name')
            ->filter(fn (mixed $tableName): bool => is_string($tableName) && preg_match('/^[A-Za-z0-9_]+$/', $tableName) === 1)
            ->unique()
            ->values()
            ->all();

        foreach ($tableNames as $tableName) {
            if (! Schema::hasTable($tableName)
                || ! Schema::hasColumn($tableName, 'id')
                || ! Schema::hasColumn($tableName, 'created_at')
                || ! Schema::hasColumn($tableName, 'updated_at')) {
                continue;
            }

            DB::table($tableName)
                ->where(function ($query): void {
                    $query->whereNull('created_at')->orWhereNull('updated_at');
                })
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($tableName): void {
                    foreach ($rows as $row) {
                        $fallback = isset($row->date) && $row->date !== null
                            ? Carbon::parse((string) $row->date)->startOfDay()
                            : now();
                        $createdAt = $row->created_at ?? $row->updated_at ?? $fallback;
                        $updatedAt = $row->updated_at ?? $row->created_at ?? $fallback;

                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update([
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                            ]);
                    }
                });
        }
    }

    /**
     * Existing timestamp values cannot be safely reverted to NULL.
     */
    public function down(): void {}
};

<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Form tables that existed before submission timestamps were persisted.
     *
     * @var list<string>
     */
    private const FORM_TABLES = [
        'ezycash',
        'ezyconvert',
        'ezytransfer',
        'pjli_cycle',
        'pjli_winback',
        'pjli_renewal',
        'pjli_ofw',
    ];

    public function up(): void
    {
        foreach (self::FORM_TABLES as $tableName) {
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

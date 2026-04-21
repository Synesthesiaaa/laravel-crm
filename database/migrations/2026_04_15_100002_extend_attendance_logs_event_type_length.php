<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Portable across MySQL + SQLite (PHPUnit uses sqlite in-memory).
        // doctrine/dbal is not installed, so we branch on driver and issue a
        // dialect-appropriate statement.
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE attendance_logs MODIFY event_type VARCHAR(80) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE attendance_logs ALTER COLUMN event_type TYPE VARCHAR(80)');

            return;
        }

        // SQLite: column widths are advisory, VARCHAR(30) already stores
        // arbitrary strings. We don't rewrite the schema; ensure the column
        // exists as a sanity check.
        if (! Schema::hasColumn('attendance_logs', 'event_type')) {
            Schema::table('attendance_logs', function ($table) {
                $table->string('event_type', 80)->nullable(false);
            });
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE attendance_logs MODIFY event_type VARCHAR(30) NOT NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE attendance_logs ALTER COLUMN event_type TYPE VARCHAR(30)');

            return;
        }
        // SQLite: no-op.
    }
};

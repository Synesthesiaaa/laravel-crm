<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE attendance_logs MODIFY event_type VARCHAR(80) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE attendance_logs MODIFY event_type VARCHAR(30) NOT NULL');
    }
};

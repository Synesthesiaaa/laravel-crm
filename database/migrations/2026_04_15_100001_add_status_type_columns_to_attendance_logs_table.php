<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('attendance_status_type_id')
                ->nullable()
                ->after('event_type')
                ->constrained('attendance_status_types')
                ->nullOnDelete();
            $table->string('direction', 10)->nullable()->after('attendance_status_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['attendance_status_type_id']);
            $table->dropColumn(['attendance_status_type_id', 'direction']);
        });
    }
};

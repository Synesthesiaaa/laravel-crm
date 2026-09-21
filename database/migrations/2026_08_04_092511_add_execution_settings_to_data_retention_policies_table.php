<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_retention_policies', function (Blueprint $table) {
            $table->string('run_mode', 16)->default('recurring')->after('is_active');
            $table->dateTime('run_at')->nullable()->after('run_mode');
            $table->string('recurrence', 16)->nullable()->after('run_at');
            $table->time('run_time')->nullable()->after('recurrence');
            $table->unsignedTinyInteger('run_day_of_week')->nullable()->after('run_time');
            $table->unsignedTinyInteger('run_day_of_month')->nullable()->after('run_day_of_week');
            $table->dateTime('next_run_at')->nullable()->after('run_day_of_month');
            $table->string('last_run_status', 16)->nullable()->after('last_deleted_count');
            $table->text('last_error')->nullable()->after('last_run_status');
        });

        $now = Carbon::now();
        $nextRunAt = $now->copy()->setTime(3, 0);

        if ($nextRunAt->lessThanOrEqualTo($now)) {
            $nextRunAt->addDay();
        }

        DB::table('data_retention_policies')->update([
            'run_mode' => 'recurring',
            'recurrence' => 'daily',
            'run_time' => '03:00:00',
            'next_run_at' => $nextRunAt,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_retention_policies', function (Blueprint $table) {
            $table->dropColumn([
                'run_mode',
                'run_at',
                'recurrence',
                'run_time',
                'run_day_of_week',
                'run_day_of_month',
                'next_run_at',
                'last_run_status',
                'last_error',
            ]);
        });
    }
};

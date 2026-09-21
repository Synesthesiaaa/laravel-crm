<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vicidial_call_history_sync_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vicidial_server_id');
            $table->unsignedBigInteger('crm_campaign_id');
            $table->string('status', 30)->default('never_synced');
            $table->dateTime('last_call_at')->nullable();
            $table->string('last_unique_id', 191)->nullable();
            $table->dateTime('last_successful_sync_at')->nullable();
            $table->dateTime('last_started_at')->nullable();
            $table->dateTime('last_failed_at')->nullable();
            $table->string('last_error_classification', 80)->nullable();
            $table->text('last_error_message')->nullable();
            $table->unsignedInteger('last_sync_duration_ms')->nullable();
            $table->unsignedBigInteger('last_rows_received')->default(0);
            $table->unsignedBigInteger('last_rows_inserted')->default(0);
            $table->unsignedBigInteger('last_rows_updated')->default(0);
            $table->dateTime('current_window_start')->nullable();
            $table->dateTime('current_window_end')->nullable();
            $table->timestamps();

            $table->unique(
                ['vicidial_server_id', 'crm_campaign_id'],
                'vicidial_call_history_sync_states_scope_unique',
            );
            $table->index(['status', 'last_successful_sync_at'], 'vicidial_call_history_sync_states_health_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vicidial_call_history_sync_states');
    }
};

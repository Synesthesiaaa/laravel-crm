<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_call_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vicidial_server_id');
            $table->unsignedBigInteger('crm_campaign_id');
            $table->string('source_table', 40);
            $table->string('source_unique_id', 191);
            $table->string('vicidial_campaign_id', 50)->nullable();
            $table->string('vicidial_list_id', 50)->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('vicidial_user', 100)->nullable();
            $table->unsignedBigInteger('crm_user_id')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('status', 80)->nullable();
            $table->string('disposition_code', 80)->nullable();
            $table->string('disposition_label', 255)->nullable();
            $table->dateTime('call_date')->nullable();
            $table->dateTime('call_started_at')->nullable();
            $table->dateTime('call_ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('talk_seconds')->nullable();
            $table->unsignedInteger('wait_seconds')->nullable();
            $table->string('direction', 20);
            $table->string('raw_end_reason', 100)->nullable();
            $table->dateTime('source_updated_at')->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['vicidial_server_id', 'crm_campaign_id', 'source_table', 'source_unique_id'],
                'telephony_call_histories_source_identity_unique',
            );
            $table->index(['crm_campaign_id', 'call_started_at'], 'telephony_call_histories_campaign_started_index');
            $table->index(['crm_campaign_id', 'vicidial_user', 'call_started_at'], 'telephony_call_histories_campaign_agent_started_index');
            $table->index(['crm_campaign_id', 'phone_number', 'call_started_at'], 'telephony_call_histories_campaign_phone_started_index');
            $table->index(['crm_campaign_id', 'status', 'call_started_at'], 'telephony_call_histories_campaign_status_started_index');
            $table->index(['crm_campaign_id', 'disposition_code'], 'telephony_call_histories_campaign_disposition_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_call_histories');
    }
};

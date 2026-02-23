<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_call_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('campaign_code', 50);
            $table->string('form_type', 50);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('agent', 255);
            $table->string('status', 50)->default('RECORDED');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->index('lead_id');
            $table->index('phone_number');
            $table->index('campaign_code');
            $table->index('created_at');
            $table->index('agent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_call_history');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_call_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('campaign_code', 50);
            $table->string('agent', 255);
            $table->string('disposition_code', 80)->default('OTHER');
            $table->string('disposition_label', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->integer('call_duration_seconds')->nullable();
            $table->text('lead_data_json')->nullable();
            $table->dateTime('called_at')->nullable();
            $table->timestamps();
            $table->index('lead_id');
            $table->index('phone_number');
            $table->index('campaign_code');
            $table->index('agent');
            $table->index('created_at');
            $table->index('called_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_call_records');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_code', 50);
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('phone_number', 50);
            $table->string('status', 30)->default('dialing');
            $table->string('linkedid', 100)->nullable()->index();
            $table->string('channel', 100)->nullable();
            $table->string('vicidial_lead_id', 50)->nullable();
            $table->timestamp('dialed_at')->nullable();
            $table->timestamp('ringing_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('disposition_code', 80)->nullable();
            $table->string('disposition_label', 255)->nullable();
            $table->timestamp('disposition_at')->nullable();
            $table->text('disposition_remarks')->nullable();
            $table->integer('call_duration_seconds')->nullable();
            $table->string('end_reason', 50)->nullable()->comment('hangup, timeout, failed, abandoned, agent, transfer');
            $table->json('metadata')->nullable()->comment('AMI/VICIdial raw payload, linked channels');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('campaign_code');
            $table->index('created_at');
            $table->index(['status', 'dialed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};

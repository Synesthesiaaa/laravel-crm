<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_capture_records', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->foreignId('call_session_id')->nullable()->constrained('call_sessions')->nullOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->string('phone_number', 50)->nullable();
            $table->string('agent', 255);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('capture_data')->nullable();
            $table->timestamps();
            $table->index('campaign_code');
            $table->index(['campaign_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_capture_records');
    }
};

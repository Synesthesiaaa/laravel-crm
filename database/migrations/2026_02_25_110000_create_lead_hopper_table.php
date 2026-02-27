<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_hopper', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->string('lead_id', 80)->nullable();
            $table->string('phone_number', 50);
            $table->string('client_name', 255)->nullable();
            $table->json('custom_data')->nullable();
            $table->string('status', 20)->default('pending'); // pending, assigned, completed
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_code', 'status']);
            $table->index('campaign_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_hopper');
    }
};

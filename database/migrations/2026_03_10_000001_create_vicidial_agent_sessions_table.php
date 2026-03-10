<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vicidial_agent_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_code', 50);
            $table->string('phone_login', 32)->nullable();
            $table->string('session_status', 32)->default('logged_out');
            $table->string('pause_code', 16)->nullable();
            $table->boolean('blended')->default(true);
            $table->text('ingroup_choices')->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('last_status_payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'campaign_code']);
            $table->index(['campaign_code', 'session_status']);
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vicidial_agent_sessions');
    }
};

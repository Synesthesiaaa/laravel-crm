<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_call_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_session_id')->nullable()->unique()->constrained('call_sessions')->nullOnDelete();
            $table->string('campaign_code', 50)->index();
            $table->foreignId('list_id')->nullable()->constrained('lead_lists')->nullOnDelete();
            $table->foreignId('lead_pk')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('vicidial_lead_id', 80)->nullable()->index();
            $table->string('phone_number', 50)->nullable()->index();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent', 255);
            $table->integer('call_duration_seconds')->nullable();

            $table->string('disposition_code', 80);
            $table->string('disposition_label', 255)->nullable();
            $table->string('disposition_source', 20)->default('agent');
            $table->text('remarks')->nullable();

            $table->json('capture_data')->nullable();
            $table->json('lead_snapshot')->nullable();

            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_edited_at')->nullable();

            $table->timestamp('called_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campaign_code', 'called_at']);
            $table->index(['agent', 'called_at']);
            $table->index(['disposition_code', 'called_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_call_dispositions');
    }
};

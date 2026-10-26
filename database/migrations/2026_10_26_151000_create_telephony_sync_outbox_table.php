<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_sync_outbox', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_type', 64);
            $table->string('idempotency_key', 191)->unique();
            $table->longText('payload_encrypted');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at', 'id'], 'telephony_outbox_dispatch_idx');
            $table->index(['status', 'claimed_at'], 'telephony_outbox_claim_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_sync_outbox');
    }
};

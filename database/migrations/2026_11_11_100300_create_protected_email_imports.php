<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('protected_email_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_campaign_id')->nullable()->constrained('email_campaigns')->nullOnDelete();
            $table->string('status', 24)->default('queued');
            $table->longText('payload');
            $table->unsignedInteger('file_count')->default(0);
            $table->unsignedInteger('prepared_files')->default(0);
            $table->unsignedBigInteger('source_bytes')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->uuid('lease_token')->nullable();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('heartbeat_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
            $table->index(['status', 'lease_expires_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('protected_email_imports');
    }
};

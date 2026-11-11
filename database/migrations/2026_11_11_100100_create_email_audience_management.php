<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('period')->nullable();
            $table->json('tags')->nullable();
            $table->string('source', 80)->default('manual');
            $table->string('campaign_code', 100)->nullable();
            $table->string('consent_status', 24)->default('unknown');
            $table->string('consent_source', 500)->nullable();
            $table->timestamp('consent_asserted_at')->nullable();
            $table->foreignId('consent_actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'email']);
            $table->index(['user_id', 'consent_status']);
            $table->index(['user_id', 'campaign_code']);
        });
        Schema::create('email_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->json('filters');
            $table->timestamps();
            $table->index(['user_id', 'name']);
        });
        Schema::create('email_audience_imports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->string('source', 20);
            $table->string('status', 24)->default('queued');
            $table->string('phase', 16)->default('preview');
            $table->string('storage_path')->nullable();
            $table->text('payload');
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedBigInteger('record_number')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('accepted')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('suppressed')->default(0);
            $table->unsignedInteger('applied')->default(0);
            $table->string('message', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['email_campaign_id', 'status']);
        });
        Schema::create('email_audience_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('import_id')->constrained('email_audience_imports')->cascadeOnDelete();
            $table->unsignedBigInteger('record_number');
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->string('employee_id')->nullable();
            $table->string('period')->nullable();
            $table->string('consent_status', 24)->nullable();
            $table->string('consent_source', 500)->nullable();
            $table->timestamp('consent_asserted_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->unique(['import_id', 'record_number']);
            $table->unique(['import_id', 'email']);
        });
        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            $table->foreignId('email_contact_id')->nullable()->constrained('email_contacts')->nullOnDelete();
            $table->string('consent_status', 24)->nullable();
            $table->string('consent_source', 500)->nullable();
            $table->timestamp('consent_asserted_at')->nullable();
            $table->string('audience_source', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('email_contact_id');
            $table->dropColumn(['consent_status', 'consent_source', 'consent_asserted_at', 'audience_source']);
        });
        Schema::dropIfExists('email_audience_rows');
        Schema::dropIfExists('email_audience_imports');
        Schema::dropIfExists('email_segments');
        Schema::dropIfExists('email_contacts');
    }
};

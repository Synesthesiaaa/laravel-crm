<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->string('purpose')->default('marketing');
            $table->string('attachment_mode')->default('none');
            $table->longText('delivery_snapshot')->nullable();
            $table->string('manifest_hash', 64)->nullable();
            $table->string('dry_run_hash', 64)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            $table->string('employee_id')->nullable();
            $table->string('period')->nullable();
            $table->string('pdf_filename')->nullable();
            $table->longText('delivery_snapshot')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->unique(['email_campaign_id', 'id'], 'email_recipient_campaign_identity');
        });
        Schema::create('email_campaign_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->unsignedBigInteger('email_campaign_recipient_id')->nullable();
            $table->foreign(['email_campaign_id', 'email_campaign_recipient_id'], 'email_document_recipient_owner')
                ->references(['email_campaign_id', 'id'])->on('email_campaign_recipients')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filename');
            $table->string('storage_key')->unique();
            $table->string('sha256', 64);
            $table->unsignedBigInteger('size');
            $table->string('mime')->default('application/pdf');
            $table->string('verification_status')->default('verified');
            $table->timestamp('expires_at')->index();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
            $table->unique(['email_campaign_id', 'filename']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_campaign_documents');
        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            $table->dropUnique('email_recipient_campaign_identity');
            $table->dropColumn(['employee_id', 'period', 'pdf_filename', 'delivery_snapshot', 'claimed_at']);
        });
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['approved_by_user_id']);
            $table->dropColumn(['purpose', 'attachment_mode', 'delivery_snapshot', 'manifest_hash', 'dry_run_hash', 'approved_at', 'approved_by_user_id']);
        });
    }
};

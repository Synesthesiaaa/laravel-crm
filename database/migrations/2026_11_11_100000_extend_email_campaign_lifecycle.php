<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->unsignedSmallInteger('management_version')->default(1);
            $table->string('audience_status', 30)->default('ready');
            $table->unsignedInteger('generation')->default(0);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->string('schedule_timezone', 64)->nullable();
            $table->string('schedule_local', 32)->nullable();
            $table->timestamp('progress_at')->nullable();
            $table->string('pause_reason', 250)->nullable();
            $table->string('provider', 30)->default('smtp');
            $table->boolean('tracking_opens')->default(false);
            $table->boolean('tracking_clicks')->default(false);
            $table->unsignedInteger('terminal_version')->default(0);
            $table->index(['created_by_user_id', 'status', 'created_at'], 'email_owner_state_date');
        });
        Schema::table('email_templates', function (Blueprint $table): void {
            $table->string('body_format', 10)->default('text');
            $table->json('design_json')->nullable();
        });
        Schema::create('email_campaign_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_campaign_recipient_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 30);
            $table->string('provider', 30)->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('entered_transport_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestamps();
            $table->index(['email_campaign_recipient_id', 'status'], 'email_attempt_recipient_state');
        });
        Schema::create('email_dispatch_intents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('generation');
            $table->unsignedBigInteger('cursor')->default(0);
            $table->unsignedInteger('dispatched_count')->default(0);
            $table->uuid('batch_id')->nullable();
            $table->string('status', 25)->default('pending');
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('heartbeat_at')->nullable();
            $table->timestamps();
            $table->unique(['email_campaign_id', 'generation']);
        });
        Schema::create('email_provider_limits', function (Blueprint $table): void {
            $table->string('account_key', 64)->primary();
            $table->unsignedInteger('window');
            $table->unsignedInteger('used')->default(0);
        });
        Schema::create('email_campaign_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('email_campaign_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['email_campaign_id', 'event_key']);
        });
        Schema::table('email_smtp_settings', function (Blueprint $table): void {
            $table->string('provider', 30)->default('smtp');
            $table->unsignedInteger('rate_per_minute')->default(60);
            $table->string('postmark_webhook_user')->nullable();
            $table->text('postmark_webhook_password')->nullable();
            $table->string('postmark_server_id', 40)->nullable();
            $table->string('postmark_message_stream', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_smtp_settings', fn (Blueprint $table) => $table->dropColumn(['provider', 'rate_per_minute', 'postmark_webhook_user', 'postmark_webhook_password', 'postmark_server_id', 'postmark_message_stream']));
        Schema::dropIfExists('email_campaign_notifications');
        Schema::dropIfExists('email_provider_limits');
        Schema::dropIfExists('email_dispatch_intents');
        Schema::dropIfExists('email_campaign_attempts');
        Schema::table('email_templates', fn (Blueprint $table) => $table->dropColumn(['body_format', 'design_json']));
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->dropIndex('email_owner_state_date');
            $table->dropIndex(['scheduled_at']);
            $table->dropColumn(['management_version', 'audience_status', 'generation', 'scheduled_at', 'schedule_timezone', 'schedule_local', 'progress_at', 'pause_reason', 'provider', 'tracking_opens', 'tracking_clicks', 'terminal_version']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            foreach (['provider_delivered_at', 'provider_bounced_at', 'provider_complained_at', 'observed_opened_at', 'observed_clicked_at', 'unsubscribed_at'] as $field) {
                $table->timestamp($field)->nullable();
            }
            $table->string('provider_bounce_type', 12)->nullable();
            $table->index(['email_campaign_id', 'provider_delivered_at'], 'email_recipient_delivery_index');
        });
        Schema::create('email_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('email');
            $table->string('reason', 32);
            $table->string('purpose', 20)->default('all');
            $table->string('source', 40);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['email', 'reason', 'purpose']);
        });
        Schema::create('email_provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 20)->default('postmark');
            $table->string('event_key', 64)->unique();
            $table->string('fingerprint', 64);
            $table->uuid('attempt_id')->index();
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->foreignId('email_campaign_recipient_id')->constrained('email_campaign_recipients')->cascadeOnDelete();
            $table->string('event_type', 24);
            $table->string('bounce_type', 12)->nullable();
            $table->string('provider_message_id', 100);
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['email_campaign_id', 'occurred_at']);
            $table->index(['processed_at', 'id']);
        });
        Schema::create('email_tracking_tokens', function (Blueprint $table): void {
            $table->id();
            $table->string('token_hash', 64)->unique();
            $table->string('kind', 20);
            $table->foreignId('email_campaign_id')->constrained('email_campaigns')->cascadeOnDelete();
            $table->foreignId('email_campaign_recipient_id')->constrained('email_campaign_recipients')->cascadeOnDelete();
            $table->uuid('attempt_id')->nullable();
            $table->text('destination')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['email_campaign_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_tracking_tokens');
        Schema::dropIfExists('email_provider_events');
        Schema::dropIfExists('email_suppressions');
        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            $table->dropIndex('email_recipient_delivery_index');
            $table->dropColumn(['provider_delivered_at', 'provider_bounced_at', 'provider_complained_at', 'observed_opened_at', 'observed_clicked_at', 'unsubscribed_at', 'provider_bounce_type']);
        });
    }
};

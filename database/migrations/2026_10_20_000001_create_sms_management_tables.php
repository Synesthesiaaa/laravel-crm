<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_gateways', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('host', 45);
            $table->unsignedSmallInteger('port')->default(80);
            $table->string('transport', 40)->default('legacy_http');
            $table->string('username', 100);
            $table->text('password');
            $table->string('firmware', 100);
            $table->boolean('enabled')->default(false);
            $table->text('verification_note')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->boolean('inbound_verified')->default(false);
            $table->timestamp('reserved_until')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sms_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_gateway_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('client_id', 100)->nullable()->unique();
            $table->text('password')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('interval_seconds')->default(30);
            $table->unsignedInteger('daily_limit')->default(100);
            $table->unsignedInteger('used_today')->default(0);
            $table->date('usage_date')->nullable();
            $table->timestamp('next_send_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamps();
            $table->unique(['sms_gateway_id', 'number']);
        });
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('body');
            $table->timestamps();
        });
        Schema::create('sms_consents', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 16);
            $table->string('category', 20);
            $table->string('source', 255);
            $table->timestamp('consented_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['phone', 'category']);
        });
        Schema::create('sms_opt_outs', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 16)->unique();
            $table->string('source', 100);
            $table->timestamps();
        });
        Schema::create('sms_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('sms_gateway_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->text('body');
            $table->string('category', 20);
            $table->string('status', 20)->default('draft')->index();
            $table->json('channel_ids');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedSmallInteger('quiet_start')->default(20);
            $table->unsignedSmallInteger('quiet_end')->default(8);
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_campaign_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('sms_channel_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lead_hopper_id')->nullable()->constrained('lead_hopper')->nullOnDelete();
            $table->foreignId('sms_consent_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('phone', 50)->nullable()->index();
            $table->string('recipient_name', 150)->nullable();
            $table->string('direction', 10)->default('outbound');
            $table->string('status', 20)->default('eligible');
            $table->string('reason', 100)->nullable();
            $table->text('body')->nullable();
            $table->uuid('idempotency_key')->unique();
            $table->string('gateway_message_id', 100)->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('enqueued_at')->nullable()->index();
            $table->timestamps();
            $table->index(['sms_campaign_id', 'status', 'id']);
            $table->index(['status', 'processing_at']);
            $table->index(['phone', 'direction', 'created_at']);
        });
        Schema::create('sms_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_channel_id')->constrained()->restrictOnDelete();
            $table->string('event_key', 100);
            $table->string('payload_hash', 64);
            $table->string('kind', 20);
            $table->foreignId('sms_message_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['sms_channel_id', 'event_key']);
        });
    }

    public function down(): void
    {
        foreach (['sms_gateway_events', 'sms_messages', 'sms_campaigns', 'sms_opt_outs', 'sms_consents', 'sms_templates', 'sms_channels', 'sms_gateways'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('lead_lists')->cascadeOnDelete();
            $table->string('campaign_code', 50);

            // ViciDial-parity columns
            $table->string('vendor_lead_code', 50)->nullable();
            $table->string('source_id', 50)->nullable();
            $table->string('phone_code', 10)->nullable();
            $table->string('phone_number', 32);
            $table->string('alt_phone', 32)->nullable();
            $table->string('title', 40)->nullable();
            $table->string('first_name', 60)->nullable();
            $table->string('middle_initial', 10)->nullable();
            $table->string('last_name', 60)->nullable();
            $table->string('address1', 100)->nullable();
            $table->string('address2', 100)->nullable();
            $table->string('address3', 100)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('state', 50)->nullable();
            $table->string('province', 50)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 50)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('security_phrase', 100)->nullable();
            $table->text('comments')->nullable();

            // Lead lifecycle
            $table->string('status', 20)->default('NEW'); // NEW, CALLBK, DNC, DROP, SALE, etc.
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('called_count')->default(0);
            $table->dateTime('last_called_at')->nullable();
            $table->dateTime('last_local_call_time')->nullable();
            $table->string('user', 20)->nullable(); // assigned agent username (vicidial parity)

            $table->json('custom_fields')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['list_id', 'status']);
            $table->index(['list_id', 'enabled', 'status']);
            $table->index(['campaign_code', 'status']);
            $table->index('phone_number');
            $table->index('vendor_lead_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};

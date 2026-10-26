<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->unsignedSmallInteger('delivery_snapshot_version')->default(1);
            $table->string('delivery_snapshot_hash', 64)->nullable();
            $table->unsignedInteger('open_count')->nullable();
            $table->unsignedInteger('attention_count')->nullable();
        });

        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            $table->string('delivery_snapshot_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table): void {
            $table->dropColumn('delivery_snapshot_hash');
        });

        Schema::table('email_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['delivery_snapshot_version', 'delivery_snapshot_hash', 'open_count', 'attention_count']);
        });
    }
};

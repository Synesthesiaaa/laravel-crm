<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_hopper', function (Blueprint $table) {
            if (! Schema::hasColumn('lead_hopper', 'priority')) {
                $table->integer('priority')->default(0)->after('custom_data');
            }
            if (! Schema::hasColumn('lead_hopper', 'attempt_count')) {
                $table->unsignedInteger('attempt_count')->default(0)->after('priority');
            }
            if (! Schema::hasColumn('lead_hopper', 'last_attempted_at')) {
                $table->dateTime('last_attempted_at')->nullable()->after('attempt_count');
            }

            $table->index(['campaign_code', 'status', 'priority'], 'lead_hopper_campaign_status_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lead_hopper', function (Blueprint $table) {
            if (Schema::hasColumn('lead_hopper', 'last_attempted_at')) {
                $table->dropColumn('last_attempted_at');
            }
            if (Schema::hasColumn('lead_hopper', 'attempt_count')) {
                $table->dropColumn('attempt_count');
            }
            if (Schema::hasColumn('lead_hopper', 'priority')) {
                $table->dropColumn('priority');
            }

            $table->dropIndex('lead_hopper_campaign_status_priority_idx');
        });
    }
};

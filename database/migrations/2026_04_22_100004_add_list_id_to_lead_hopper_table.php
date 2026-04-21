<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_hopper', function (Blueprint $table) {
            if (! Schema::hasColumn('lead_hopper', 'list_id')) {
                $table->unsignedBigInteger('list_id')->nullable()->after('campaign_code');
                $table->index('list_id', 'lead_hopper_list_id_idx');
            }
            if (! Schema::hasColumn('lead_hopper', 'lead_pk')) {
                $table->unsignedBigInteger('lead_pk')->nullable()->after('list_id');
                $table->index('lead_pk', 'lead_hopper_lead_pk_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_hopper', function (Blueprint $table) {
            if (Schema::hasColumn('lead_hopper', 'lead_pk')) {
                $table->dropIndex('lead_hopper_lead_pk_idx');
                $table->dropColumn('lead_pk');
            }
            if (Schema::hasColumn('lead_hopper', 'list_id')) {
                $table->dropIndex('lead_hopper_list_id_idx');
                $table->dropColumn('list_id');
            }
        });
    }
};

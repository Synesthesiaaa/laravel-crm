<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crm_call_history', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('agent')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(
                ['user_id', 'campaign_code', 'created_at'],
                'crm_call_history_user_campaign_created_index',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_call_history', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex('crm_call_history_user_campaign_created_index');
            $table->dropColumn('user_id');
        });
    }
};

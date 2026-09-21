<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_disposition_records', function (Blueprint $table) {
            if (! Schema::hasColumn('campaign_disposition_records', 'call_session_id')) {
                $table->foreignId('call_session_id')->nullable()->after('id')
                    ->constrained('call_sessions')->nullOnDelete();
            }
        });

        $indexes = Schema::getIndexes('campaign_disposition_records');
        $hasUnique = collect($indexes)->contains(
            fn (array $index): bool => ($index['name'] ?? null) === 'cdr_call_session_id_unique',
        );

        if (! $hasUnique) {
            Schema::table('campaign_disposition_records', function (Blueprint $table) {
                $table->unique('call_session_id', 'cdr_call_session_id_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('campaign_disposition_records', function (Blueprint $table) {
            $indexes = Schema::getIndexes('campaign_disposition_records');
            $hasUnique = collect($indexes)->contains(
                fn (array $index): bool => ($index['name'] ?? null) === 'cdr_call_session_id_unique',
            );

            if ($hasUnique) {
                $table->dropUnique('cdr_call_session_id_unique');
            }
            if (Schema::hasColumn('campaign_disposition_records', 'call_session_id')) {
                $table->dropForeign(['call_session_id']);
                $table->dropColumn('call_session_id');
            }
        });
    }
};

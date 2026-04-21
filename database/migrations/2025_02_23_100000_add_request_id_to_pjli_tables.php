<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['pjli_cycle', 'pjli_winback', 'pjli_renewal', 'pjli_ofw'];
        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (Schema::hasColumn($tableName, 'request_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('request_id', 255)->nullable()->after('agent');
                $table->index('request_id');
            });
        }
    }

    public function down(): void
    {
        $tables = ['pjli_cycle', 'pjli_winback', 'pjli_renewal', 'pjli_ofw'];
        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }
            if (! Schema::hasColumn($tableName, 'request_id')) {
                continue;
            }
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['request_id']);
                $table->dropColumn('request_id');
            });
        }
    }
};

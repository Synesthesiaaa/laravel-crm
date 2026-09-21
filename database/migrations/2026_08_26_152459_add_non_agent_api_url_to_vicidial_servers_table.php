<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vicidial_servers', function (Blueprint $table) {
            $table->string('non_agent_api_url', 500)->nullable()->after('api_url');
        });
    }

    public function down(): void
    {
        Schema::table('vicidial_servers', function (Blueprint $table) {
            $table->dropColumn('non_agent_api_url');
        });
    }
};

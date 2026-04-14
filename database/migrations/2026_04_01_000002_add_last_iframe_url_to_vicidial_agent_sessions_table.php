<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vicidial_agent_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('vicidial_agent_sessions', 'last_iframe_url')) {
                $table->text('last_iframe_url')->nullable()->after('ingroup_choices');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vicidial_agent_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('vicidial_agent_sessions', 'last_iframe_url')) {
                $table->dropColumn('last_iframe_url');
            }
        });
    }
};

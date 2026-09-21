<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_screen_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_screen_fields', 'visibility')) {
                $table->json('visibility')->nullable()->after('is_required');
            }
        });

        Schema::table('form_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('form_fields', 'visibility')) {
                $table->json('visibility')->nullable()->after('field_width');
            }
        });
    }

    public function down(): void
    {
        Schema::table('agent_screen_fields', function (Blueprint $table) {
            if (Schema::hasColumn('agent_screen_fields', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });

        Schema::table('form_fields', function (Blueprint $table) {
            if (Schema::hasColumn('form_fields', 'visibility')) {
                $table->dropColumn('visibility');
            }
        });
    }
};

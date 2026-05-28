<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_screen_fields', function (Blueprint $table) {
            $table->string('vici_field', 80)->nullable()->after('field_key');
        });
    }

    public function down(): void
    {
        Schema::table('agent_screen_fields', function (Blueprint $table) {
            $table->dropColumn('vici_field');
        });
    }
};

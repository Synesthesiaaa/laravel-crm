<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_screen_fields', function (Blueprint $table) {
            $table->string('field_type', 20)->default('text')->after('field_label');
            $table->string('direction', 10)->default('get')->after('field_type');
            $table->text('options')->nullable()->after('direction');
            $table->string('placeholder', 120)->nullable()->after('options');
            $table->boolean('is_required')->default(false)->after('placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('agent_screen_fields', function (Blueprint $table) {
            $table->dropColumn([
                'field_type',
                'direction',
                'options',
                'placeholder',
                'is_required',
            ]);
        });
    }
};

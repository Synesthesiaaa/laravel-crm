<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_screen_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('agent_screen_fields', 'field_type')) {
                $table->string('field_type', 20)->default('text')->after('field_label');
            }
            if (! Schema::hasColumn('agent_screen_fields', 'direction')) {
                $table->string('direction', 10)->default('get')->after('field_type');
            }
            if (! Schema::hasColumn('agent_screen_fields', 'options')) {
                $table->text('options')->nullable()->after('direction');
            }
            if (! Schema::hasColumn('agent_screen_fields', 'placeholder')) {
                $table->string('placeholder', 120)->nullable()->after('options');
            }
            if (! Schema::hasColumn('agent_screen_fields', 'is_required')) {
                $table->boolean('is_required')->default(false)->after('placeholder');
            }
        });
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'field_type',
            'direction',
            'options',
            'placeholder',
            'is_required',
        ], fn (string $column): bool => Schema::hasColumn('agent_screen_fields', $column)));

        if ($columns === []) {
            return;
        }

        Schema::table('agent_screen_fields', function (Blueprint $table) {
            $table->dropColumn($columns);
        });
    }
};

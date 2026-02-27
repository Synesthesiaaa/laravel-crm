<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'predictive_enabled')) {
                $table->boolean('predictive_enabled')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('campaigns', 'predictive_delay_seconds')) {
                $table->unsignedInteger('predictive_delay_seconds')->default(3)->after('predictive_enabled');
            }
            if (! Schema::hasColumn('campaigns', 'predictive_max_attempts')) {
                $table->unsignedInteger('predictive_max_attempts')->default(3)->after('predictive_delay_seconds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            foreach (['predictive_enabled', 'predictive_delay_seconds', 'predictive_max_attempts'] as $column) {
                if (Schema::hasColumn('campaigns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

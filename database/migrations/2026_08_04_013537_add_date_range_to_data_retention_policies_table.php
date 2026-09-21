<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_retention_policies', function (Blueprint $table): void {
            $table->renameColumn('cutoff_date', 'to_date');
            $table->date('from_date')->nullable()->after('to_date');
        });
    }

    public function down(): void
    {
        Schema::table('data_retention_policies', function (Blueprint $table): void {
            $table->dropColumn('from_date');
            $table->renameColumn('to_date', 'cutoff_date');
        });
    }
};

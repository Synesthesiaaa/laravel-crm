<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_retention_policies', function (Blueprint $table): void {
            $table->string('deletion_mode', 32)->default('whole_record')->after('cutoff_date');
            $table->json('selected_fields')->nullable()->after('deletion_mode');
        });
    }

    public function down(): void
    {
        Schema::table('data_retention_policies', function (Blueprint $table): void {
            $table->dropColumn(['deletion_mode', 'selected_fields']);
        });
    }
};

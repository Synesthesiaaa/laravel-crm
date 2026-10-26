<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->boolean('draft_safe')->default(false)->after('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->dropColumn('draft_safe');
        });
    }
};

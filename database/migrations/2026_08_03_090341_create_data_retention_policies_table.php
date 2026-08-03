<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->date('cutoff_date');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('last_deleted_count')->default(0);
            $table->timestamps();
            $table->unique('form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_policies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_list_fields', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->string('field_key', 80);
            $table->string('field_label', 120);
            $table->string('field_type', 20)->default('text'); // text, number, email, date, select, textarea
            $table->text('field_options')->nullable(); // JSON array for select options
            $table->boolean('is_standard')->default(false); // built-in vicidial column vs custom
            $table->boolean('visible')->default(true);
            $table->boolean('exportable')->default(true);
            $table->boolean('importable')->default(true);
            $table->integer('field_order')->default(0);
            $table->timestamps();

            $table->unique(['campaign_code', 'field_key']);
            $table->index(['campaign_code', 'field_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_list_fields');
    }
};

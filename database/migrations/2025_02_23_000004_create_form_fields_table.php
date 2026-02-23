<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->string('form_type', 50);
            $table->string('field_name', 100);
            $table->string('field_label', 255);
            $table->string('field_type', 20)->default('text');
            $table->boolean('is_required')->default(false);
            $table->integer('field_order')->default(0);
            $table->text('options')->nullable();
            $table->string('vici_params', 100)->nullable();
            $table->string('field_width', 20)->default('full');
            $table->timestamps();
            $table->unique(['campaign_code', 'form_type', 'field_name']);
            $table->index(['campaign_code', 'form_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};

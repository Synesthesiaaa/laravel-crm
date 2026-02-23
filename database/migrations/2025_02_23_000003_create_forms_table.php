<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->string('form_code', 50);
            $table->string('name', 255);
            $table->string('table_name', 100);
            $table->string('color', 50)->default('blue');
            $table->string('icon', 50)->default('form');
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['campaign_code', 'form_code']);
            $table->index('campaign_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forms');
    }
};

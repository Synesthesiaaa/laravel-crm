<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposition_codes', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50)->default('');
            $table->string('code', 80);
            $table->string('label', 255);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['campaign_code', 'code']);
            $table->index('campaign_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposition_codes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_screen_fields', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->string('field_key', 80);
            $table->string('field_label', 120);
            $table->integer('field_order')->default(0);
            $table->string('field_width', 20)->default('full');
            $table->timestamps();
            $table->unique(['campaign_code', 'field_key']);
            $table->index('campaign_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_screen_fields');
    }
};

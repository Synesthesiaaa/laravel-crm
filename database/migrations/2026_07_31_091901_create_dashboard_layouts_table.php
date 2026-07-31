<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50)->unique();
            $table->json('layout');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_layouts');
    }
};

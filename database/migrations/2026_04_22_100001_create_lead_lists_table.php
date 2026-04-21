<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_lists', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_code', 50);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->dateTime('reset_time')->nullable();
            $table->integer('display_order')->default(0);
            $table->unsignedInteger('leads_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campaign_code', 'active']);
            $table->index(['campaign_code', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_lists');
    }
};

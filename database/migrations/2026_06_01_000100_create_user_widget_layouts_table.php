<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_widget_layouts')) {
            Schema::create('user_widget_layouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('widget_key', 64);
                $table->json('layout');
                $table->timestamps();

                $table->unique(['user_id', 'widget_key']);
                $table->index('widget_key');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_widget_layouts');
    }
};

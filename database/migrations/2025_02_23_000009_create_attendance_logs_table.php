<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->dateTime('event_time');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('user_id');
            $table->index('event_time');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};

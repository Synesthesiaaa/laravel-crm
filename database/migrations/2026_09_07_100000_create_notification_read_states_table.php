<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_read_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_key', 191);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'item_key'], 'notification_read_states_user_item_unique');
            $table->index(['user_id', 'read_at'], 'notification_read_states_user_read_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_read_states');
    }
};

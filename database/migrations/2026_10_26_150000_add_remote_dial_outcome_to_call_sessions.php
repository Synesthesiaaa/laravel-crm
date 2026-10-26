<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->string('remote_dial_outcome', 24)->nullable()->after('status');
            $table->timestamp('remote_dial_outcome_at')->nullable()->after('remote_dial_outcome');
            $table->index(['remote_dial_outcome', 'status'], 'call_sessions_remote_dial_outcome_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table): void {
            $table->dropIndex('call_sessions_remote_dial_outcome_status_idx');
            $table->dropColumn(['remote_dial_outcome', 'remote_dial_outcome_at']);
        });
    }
};

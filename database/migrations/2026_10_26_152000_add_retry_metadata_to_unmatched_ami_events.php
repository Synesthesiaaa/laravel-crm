<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unmatched_ami_events', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->index(['processed', 'last_attempted_at'], 'unmatched_ami_retry_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('unmatched_ami_events', function (Blueprint $table): void {
            $table->dropIndex('unmatched_ami_retry_due_idx');
            $table->dropColumn(['attempt_count', 'last_attempted_at', 'last_error_code']);
        });
    }
};

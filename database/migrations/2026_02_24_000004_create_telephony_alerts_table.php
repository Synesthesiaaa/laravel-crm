<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telephony_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50); // stale_corrected, unmatched_ami, reconciliation_error, dead_letter, vicidial_unreachable
            $table->string('severity', 20)->default('warning'); // info, warning, critical
            $table->string('message');
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::table('telephony_alerts', function (Blueprint $table) {
            $table->index(['type', 'created_at']);
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_alerts');
    }
};

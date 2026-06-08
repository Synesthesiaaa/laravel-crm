<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('telephony_alerts')) {
            Schema::create('telephony_alerts', function (Blueprint $table) {
                $table->id();
                $table->string('type', 50); // stale_corrected, unmatched_ami, reconciliation_error, dead_letter, vicidial_unreachable
                $table->string('severity', 20)->default('warning'); // info, warning, critical
                $table->string('message');
                $table->json('context')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        Schema::table('telephony_alerts', function (Blueprint $table) {
            $indexes = collect(Schema::getIndexes('telephony_alerts'))->pluck('name')->all();

            if (! in_array('telephony_alerts_type_created_at_index', $indexes, true)) {
                $table->index(['type', 'created_at']);
            }
            if (! in_array('telephony_alerts_resolved_at_index', $indexes, true)) {
                $table->index('resolved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_alerts');
    }
};

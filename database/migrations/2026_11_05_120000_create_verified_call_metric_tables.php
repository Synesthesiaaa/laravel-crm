<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $identityCollation = in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'utf8mb4_bin' : null;
        Schema::create('telephony_metric_attempts', function (Blueprint $table) use ($identityCollation): void {
            $table->id();
            $table->foreignId('crm_campaign_id')->constrained('campaigns')->restrictOnDelete();
            $table->foreignId('vicidial_server_id')->constrained('vicidial_servers')->restrictOnDelete();
            $table->string('campaign', 50);
            $table->string('attempt_key', 100)->collation($identityCollation);
            $table->string('lead_key', 100)->nullable()->collation($identityCollation);
            $table->dateTime('started_at', 6);
            $table->dateTime('answered_at', 6)->nullable();
            $table->dateTime('agent_connected_at', 6)->nullable();
            $table->dateTime('ended_at', 6)->nullable();
            foreach (['valid_attempt', 'answered', 'live_answer', 'agent_connected', 'conversation_verified'] as $flag) {
                $table->boolean($flag)->nullable();
            }
            $table->string('outcome', 50)->default('unknown');
            $table->string('source_reference', 255);
            $table->string('definition_version', 30);
            $table->string('evidence_hash', 64);
            $table->dateTime('observed_at', 6);
            $table->timestamps();
            $table->unique(['vicidial_server_id', 'crm_campaign_id', 'attempt_key'], 'metric_attempt_identity');
            $table->index(['crm_campaign_id', 'vicidial_server_id', 'campaign', 'started_at'], 'metric_attempt_cohort');
        });
        Schema::create('telephony_metric_agents', function (Blueprint $table) use ($identityCollation): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('telephony_metric_attempts')->cascadeOnDelete();
            $table->string('login', 100)->collation($identityCollation);
            $table->boolean('conversation_verified')->nullable();
            $table->unique(['attempt_id', 'login']);
            $table->index(['login', 'attempt_id']);
        });
        Schema::create('telephony_metric_coverage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crm_campaign_id')->constrained('campaigns')->restrictOnDelete();
            $table->foreignId('vicidial_server_id')->constrained('vicidial_servers')->restrictOnDelete();
            $table->string('campaign', 50);
            $table->string('metric', 30);
            $table->dateTime('start_at', 6);
            $table->dateTime('end_at', 6);
            $table->dateTime('observed_through', 6);
            $table->string('source_reference', 255);
            $table->string('definition_version', 30);
            $table->timestamps();
            $table->unique(['crm_campaign_id', 'vicidial_server_id', 'campaign', 'metric', 'start_at', 'end_at'], 'metric_coverage_identity');
            $table->index(['crm_campaign_id', 'vicidial_server_id', 'campaign', 'metric'], 'metric_coverage_scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telephony_metric_agents');
        Schema::dropIfExists('telephony_metric_coverage');
        Schema::dropIfExists('telephony_metric_attempts');
    }
};

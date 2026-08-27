<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_vicidial_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('vicidial_server_id')->constrained('vicidial_servers')->cascadeOnDelete();
            $table->string('vicidial_campaign_code', 50);
            $table->boolean('is_enabled')->default(true);
            $table->string('status', 20)->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(
                ['campaign_id', 'vicidial_server_id', 'vicidial_campaign_code'],
                'campaign_vicidial_mappings_unique_scope',
            );
            $table->index(['campaign_id', 'is_enabled', 'status']);
        });

        $campaignIds = DB::table('campaigns')->pluck('id', 'code');
        $legacyMappings = DB::table('vicidial_servers')
            ->select(['id', 'campaign_code', 'is_active'])
            ->whereNull('deleted_at')
            ->get()
            ->filter(fn (object $server): bool => $campaignIds->has($server->campaign_code))
            ->map(fn (object $server): array => [
                'campaign_id' => $campaignIds->get($server->campaign_code),
                'vicidial_server_id' => $server->id,
                'vicidial_campaign_code' => $server->campaign_code,
                'is_enabled' => (bool) $server->is_active,
                'status' => $server->is_active ? 'active' : 'disabled',
                'last_seen_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        if ($legacyMappings !== []) {
            DB::table('campaign_vicidial_mappings')->insertOrIgnore($legacyMappings);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_vicidial_mappings');
    }
};

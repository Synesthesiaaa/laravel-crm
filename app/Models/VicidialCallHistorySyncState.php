<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VicidialCallHistorySyncState extends Model
{
    /** @use HasFactory<\Database\Factories\VicidialCallHistorySyncStateFactory> */
    use HasFactory;

    public const STATUS_NEVER_SYNCED = 'never_synced';

    public const STATUS_RUNNING = 'running';

    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'vicidial_server_id',
        'crm_campaign_id',
        'status',
        'last_call_at',
        'last_unique_id',
        'last_successful_sync_at',
        'last_started_at',
        'last_failed_at',
        'last_error_classification',
        'last_error_message',
        'last_sync_duration_ms',
        'last_rows_received',
        'last_rows_inserted',
        'last_rows_updated',
        'current_window_start',
        'current_window_end',
    ];

    protected function casts(): array
    {
        return [
            'vicidial_server_id' => 'integer',
            'crm_campaign_id' => 'integer',
            'last_call_at' => 'datetime',
            'last_successful_sync_at' => 'datetime',
            'last_started_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'last_sync_duration_ms' => 'integer',
            'last_rows_received' => 'integer',
            'last_rows_inserted' => 'integer',
            'last_rows_updated' => 'integer',
            'current_window_start' => 'datetime',
            'current_window_end' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'crm_campaign_id');
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(VicidialServer::class, 'vicidial_server_id');
    }

    public function scopeForScope(Builder $query, int $serverId, int $campaignId): Builder
    {
        return $query
            ->where('vicidial_server_id', $serverId)
            ->where('crm_campaign_id', $campaignId);
    }
}

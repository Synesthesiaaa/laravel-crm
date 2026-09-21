<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CampaignVicidialMapping extends Model
{
    /** @use HasFactory<\Database\Factories\CampaignVicidialMappingFactory> */
    use HasFactory, LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUS_STALE = 'stale';

    public const STATUS_UNAVAILABLE = 'unavailable';

    protected $fillable = [
        'campaign_id',
        'vicidial_server_id',
        'vicidial_campaign_code',
        'is_enabled',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'campaign_id' => 'integer',
            'vicidial_server_id' => 'integer',
            'is_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['campaign_id', 'vicidial_server_id', 'vicidial_campaign_code', 'is_enabled', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function vicidialServer(): BelongsTo
    {
        return $this->belongsTo(VicidialServer::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->enabled()->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForServer(Builder $query, int $serverId): Builder
    {
        return $query->where('vicidial_server_id', $serverId);
    }
}

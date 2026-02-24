<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignDispositionRecord extends Model
{
    protected $fillable = [
        'campaign_code',
        'lead_id',
        'phone_number',
        'agent',
        'disposition_code',
        'disposition_label',
        'remarks',
        'call_duration_seconds',
        'lead_data_json',
        'called_at',
    ];

    protected function casts(): array
    {
        return [
            'called_at'      => 'datetime',
            'lead_data_json' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_code', 'code');
    }

    public function scopeForCampaign(Builder $query, string $campaignCode): Builder
    {
        return $query->where('campaign_code', $campaignCode);
    }

    public function scopeForAgent(Builder $query, string $agent): Builder
    {
        return $query->where('agent', $agent);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('called_at', '>=', now()->subDays($days));
    }
}

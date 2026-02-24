<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmCallHistory extends Model
{
    protected $table = 'crm_call_history';

    public $timestamps = true;

    protected $fillable = [
        'lead_id',
        'phone_number',
        'campaign_code',
        'form_type',
        'record_id',
        'agent',
        'status',
        'remarks',
    ];

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
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}

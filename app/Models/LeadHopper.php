<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadHopper extends Model
{
    protected $table = 'lead_hopper';

    protected $fillable = [
        'campaign_code',
        'lead_id',
        'phone_number',
        'client_name',
        'custom_data',
        'priority',
        'attempt_count',
        'last_attempted_at',
        'status',
        'assigned_to_user_id',
        'assigned_at',
        'completed_at',
    ];

    protected $casts = [
        'custom_data' => 'array',
        'priority' => 'integer',
        'attempt_count' => 'integer',
        'last_attempted_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForCampaign($query, string $campaignCode)
    {
        return $query->where('campaign_code', $campaignCode);
    }
}

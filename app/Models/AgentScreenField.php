<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AgentScreenField extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_code',
        'field_key',
        'vici_field',
        'field_label',
        'field_type',
        'direction',
        'options',
        'placeholder',
        'is_required',
        'field_order',
        'field_width',
    ];

    protected $casts = [
        'options' => 'array',
        'is_required' => 'boolean',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_code', 'code');
    }

    public function scopeForCampaign(Builder $query, string $campaignCode): Builder
    {
        return $query->where('campaign_code', $campaignCode);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('field_order')->orderBy('id');
    }
}

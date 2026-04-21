<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeadList extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'lead_lists';

    protected $fillable = [
        'campaign_code',
        'name',
        'description',
        'active',
        'reset_time',
        'display_order',
        'leads_count',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'reset_time' => 'datetime',
            'display_order' => 'integer',
            'leads_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['campaign_code', 'name', 'active', 'display_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_code', 'code');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'list_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeForCampaign(Builder $query, string $campaignCode): Builder
    {
        return $query->where('campaign_code', $campaignCode);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('id');
    }
}

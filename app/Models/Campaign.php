<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Campaign extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'is_active',
        'predictive_enabled',
        'predictive_delay_seconds',
        'predictive_max_attempts',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'predictive_enabled' => 'boolean',
            'predictive_delay_seconds' => 'integer',
            'predictive_max_attempts' => 'integer',
        ];
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class, 'campaign_code', 'code');
    }

    public function dispositionCodes(): HasMany
    {
        return $this->hasMany(DispositionCode::class, 'campaign_code', 'code');
    }

    public function vicidialServers(): HasMany
    {
        return $this->hasMany(VicidialServer::class, 'campaign_code', 'code');
    }

    public function agentScreenFields(): HasMany
    {
        return $this->hasMany(AgentScreenField::class, 'campaign_code', 'code');
    }

    public function callHistory(): HasMany
    {
        return $this->hasMany(CrmCallHistory::class, 'campaign_code', 'code');
    }

    public function dispositionRecords(): HasMany
    {
        return $this->hasMany(CampaignDispositionRecord::class, 'campaign_code', 'code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('id');
    }
}

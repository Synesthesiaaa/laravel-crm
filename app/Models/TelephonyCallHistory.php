<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelephonyCallHistory extends Model
{
    /** @use HasFactory<\Database\Factories\TelephonyCallHistoryFactory> */
    use HasFactory;

    protected $fillable = [
        'vicidial_server_id',
        'crm_campaign_id',
        'source_table',
        'source_unique_id',
        'vicidial_campaign_id',
        'vicidial_list_id',
        'lead_id',
        'vicidial_user',
        'crm_user_id',
        'phone_number',
        'status',
        'disposition_code',
        'disposition_label',
        'call_date',
        'call_started_at',
        'call_ended_at',
        'duration_seconds',
        'talk_seconds',
        'wait_seconds',
        'direction',
        'raw_end_reason',
        'source_updated_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'vicidial_server_id' => 'integer',
            'crm_campaign_id' => 'integer',
            'lead_id' => 'integer',
            'crm_user_id' => 'integer',
            'duration_seconds' => 'integer',
            'talk_seconds' => 'integer',
            'wait_seconds' => 'integer',
            'call_date' => 'datetime',
            'call_started_at' => 'datetime',
            'call_ended_at' => 'datetime',
            'source_updated_at' => 'datetime',
            'synced_at' => 'datetime',
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

    public function crmUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'crm_user_id')->withTrashed();
    }

    public function scopeForCampaign(Builder $query, int $campaignId): Builder
    {
        return $query->where('crm_campaign_id', $campaignId);
    }

    public function scopeForSourceCall(
        Builder $query,
        int $serverId,
        int $campaignId,
        string $sourceTable,
        string $sourceUniqueId,
    ): Builder {
        return $query
            ->where('vicidial_server_id', $serverId)
            ->where('crm_campaign_id', $campaignId)
            ->where('source_table', $sourceTable)
            ->where('source_unique_id', $sourceUniqueId);
    }
}

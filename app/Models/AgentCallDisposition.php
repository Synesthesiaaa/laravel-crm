<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AgentCallDisposition extends Model
{
    use LogsActivity, SoftDeletes;

    public const SOURCE_AGENT = 'agent';

    public const SOURCE_VICIDIAL_WEBHOOK = 'vicidial_webhook';

    public const SOURCE_VICIDIAL_POLL = 'vicidial_poll';

    protected $table = 'agent_call_dispositions';

    protected $fillable = [
        'call_session_id',
        'campaign_code',
        'list_id',
        'lead_pk',
        'vicidial_lead_id',
        'phone_number',
        'user_id',
        'agent',
        'call_duration_seconds',
        'disposition_code',
        'disposition_label',
        'disposition_source',
        'remarks',
        'capture_data',
        'lead_snapshot',
        'last_edited_by_user_id',
        'last_edited_at',
        'called_at',
    ];

    protected function casts(): array
    {
        return [
            'capture_data' => 'array',
            'lead_snapshot' => 'array',
            'called_at' => 'datetime',
            'last_edited_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'disposition_code',
                'disposition_label',
                'capture_data',
                'remarks',
                'last_edited_by_user_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by_user_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'lead_pk');
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(LeadList::class, 'list_id');
    }

    public function scopeForCampaign(Builder $query, string $campaignCode): Builder
    {
        return $query->where('campaign_code', $campaignCode);
    }
}

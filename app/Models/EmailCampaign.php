<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    protected $fillable = ['email_template_id', 'created_by_user_id', 'name', 'status', 'recipient_count', 'batch_size', 'delay_seconds', 'purpose', 'attachment_mode', 'started_at', 'completed_at', 'management_version', 'audience_status', 'generation', 'scheduled_at', 'schedule_timezone', 'schedule_local', 'provider', 'tracking_opens', 'tracking_clicks'];

    protected $hidden = ['delivery_snapshot', 'delivery_snapshot_hash'];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'approved_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'progress_at' => 'datetime',
            'management_version' => 'integer',
            'generation' => 'integer',
            'approval_request' => 'array',
            'approval_requested_at' => 'datetime',
            'tracking_opens' => 'boolean',
            'tracking_clicks' => 'boolean',
            'delivery_snapshot' => 'encrypted:array',
            'delivery_snapshot_version' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'skipped_count' => 'integer',
            'open_count' => 'integer',
            'attention_count' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailCampaignRecipient::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmailCampaignDocument::class);
    }

    public function needsReview(): bool
    {
        return $this->purpose !== 'marketing' || $this->attachment_mode !== 'none';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

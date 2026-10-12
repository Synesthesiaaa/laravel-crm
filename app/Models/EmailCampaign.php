<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailCampaign extends Model
{
    protected $fillable = ['email_template_id', 'created_by_user_id', 'name', 'status', 'recipient_count', 'batch_size', 'delay_seconds', 'purpose', 'attachment_mode', 'started_at', 'completed_at'];

    protected $hidden = ['delivery_snapshot'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'completed_at' => 'datetime', 'approved_at' => 'datetime', 'delivery_snapshot' => 'encrypted:array'];
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
}

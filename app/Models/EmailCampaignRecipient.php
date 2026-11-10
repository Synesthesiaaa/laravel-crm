<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCampaignRecipient extends Model
{
    protected $fillable = ['email_campaign_id', 'email', 'name', 'status', 'error', 'sent_at', 'employee_id', 'period', 'pdf_filename'];

    protected $hidden = ['delivery_snapshot', 'delivery_snapshot_hash'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'claimed_at' => 'datetime', 'delivery_snapshot' => 'encrypted:array', 'provider_delivered_at' => 'datetime', 'provider_bounced_at' => 'datetime', 'provider_complained_at' => 'datetime', 'observed_opened_at' => 'datetime', 'observed_clicked_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class, 'email_campaign_id');
    }
}

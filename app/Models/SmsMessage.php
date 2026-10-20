<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'processing_at' => 'datetime', 'submitted_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'enqueued_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(SmsCampaign::class, 'sms_campaign_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(SmsChannel::class, 'sms_channel_id');
    }

    public function consent(): BelongsTo
    {
        return $this->belongsTo(SmsConsent::class, 'sms_consent_id');
    }

    public function optOut(): BelongsTo
    {
        return $this->belongsTo(SmsOptOut::class, 'phone', 'phone');
    }
}

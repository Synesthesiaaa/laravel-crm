<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsCampaign extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['body' => 'encrypted', 'channel_ids' => 'array', 'scheduled_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(SmsGateway::class, 'sms_gateway_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

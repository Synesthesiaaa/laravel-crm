<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCaptureRecord extends Model
{
    protected $fillable = [
        'campaign_code',
        'call_session_id',
        'lead_id',
        'phone_number',
        'agent',
        'user_id',
        'capture_data',
    ];

    protected $casts = [
        'capture_data' => 'array',
    ];

    public function callSession(): BelongsTo
    {
        return $this->belongsTo(CallSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

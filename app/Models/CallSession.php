<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallSession extends Model
{
    use HasFactory;
    protected $table = 'call_sessions';

    public const STATUS_DIALING = 'dialing';
    public const STATUS_RINGING = 'ringing';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_IN_CALL = 'in_call';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_TRANSFERRING = 'transferring';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ABANDONED = 'abandoned';

    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_ABANDONED,
    ];

    protected $fillable = [
        'user_id',
        'campaign_code',
        'lead_id',
        'phone_number',
        'status',
        'linkedid',
        'channel',
        'vicidial_lead_id',
        'dialed_at',
        'ringing_at',
        'answered_at',
        'ended_at',
        'disposition_code',
        'disposition_label',
        'disposition_at',
        'disposition_remarks',
        'call_duration_seconds',
        'end_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'dialed_at' => 'datetime',
            'ringing_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'disposition_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_code', 'code');
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }
}

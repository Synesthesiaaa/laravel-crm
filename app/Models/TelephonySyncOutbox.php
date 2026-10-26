<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelephonySyncOutbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNKNOWN = 'unknown';

    protected $table = 'telephony_sync_outbox';

    protected $fillable = [
        'operation_type',
        'idempotency_key',
        'payload_encrypted',
        'status',
        'attempt_count',
        'available_at',
        'claimed_at',
        'last_dispatched_at',
        'completed_at',
        'last_error_code',
        'last_error_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'available_at' => 'datetime',
            'claimed_at' => 'datetime',
            'last_dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }
}

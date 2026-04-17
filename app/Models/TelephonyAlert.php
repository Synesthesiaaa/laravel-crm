<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelephonyAlert extends Model
{
    protected $table = 'telephony_alerts';

    protected $fillable = ['type', 'severity', 'message', 'context', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public const TYPE_STALE_CORRECTED = 'stale_corrected';

    public const TYPE_UNMATCHED_AMI = 'unmatched_ami';

    public const TYPE_RECONCILIATION_ERROR = 'reconciliation_error';

    public const TYPE_DEAD_LETTER = 'dead_letter';

    public const TYPE_VICIDIAL_UNREACHABLE = 'vicidial_unreachable';

    public const SEVERITY_INFO = 'info';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_CRITICAL = 'critical';

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}

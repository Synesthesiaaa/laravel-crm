<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    public const SYSTEM_EVENT_TYPES = ['login', 'logout'];

    public const DIRECTION_START = 'start';

    public const DIRECTION_END = 'end';

    protected $fillable = [
        'user_id',
        'event_type',
        'attendance_status_type_id',
        'direction',
        'event_time',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'event_time' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusType(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatusType::class, 'attendance_status_type_id');
    }

    public function eventDisplayLabel(): string
    {
        if (in_array($this->event_type, self::SYSTEM_EVENT_TYPES, true)) {
            return strtoupper($this->event_type);
        }

        $base = $this->relationLoaded('statusType') && $this->statusType
            ? $this->statusType->label
            : strtoupper($this->event_type);

        if ($this->direction === self::DIRECTION_START) {
            return $base.' (Start)';
        }
        if ($this->direction === self::DIRECTION_END) {
            return $base.' (End)';
        }

        return $base;
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('event_time', $date);
    }

    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('event_time', '>=', now()->subDays($days));
    }
}

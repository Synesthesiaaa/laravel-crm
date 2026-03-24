<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'user_id',
        'event_type',
        'pause_code',
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

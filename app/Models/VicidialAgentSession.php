<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VicidialAgentSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'campaign_code',
        'phone_login',
        'session_status',
        'pause_code',
        'blended',
        'ingroup_choices',
        'last_iframe_url',
        'logged_in_at',
        'last_synced_at',
        'last_status_payload',
    ];

    protected function casts(): array
    {
        return [
            'blended' => 'boolean',
            'logged_in_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_status_payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

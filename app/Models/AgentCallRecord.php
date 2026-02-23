<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentCallRecord extends Model
{
    protected $table = 'agent_call_records';

    protected $fillable = [
        'lead_id',
        'phone_number',
        'campaign_code',
        'agent',
        'disposition_code',
        'disposition_label',
        'remarks',
        'call_duration_seconds',
        'lead_data_json',
        'called_at',
    ];

    protected function casts(): array
    {
        return [
            'called_at' => 'datetime',
        ];
    }
}

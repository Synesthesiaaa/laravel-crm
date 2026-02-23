<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignDispositionRecord extends Model
{
    protected $fillable = [
        'campaign_code',
        'lead_id',
        'phone_number',
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentScreenField extends Model
{
    protected $fillable = [
        'campaign_code',
        'field_key',
        'field_label',
        'field_order',
        'field_width',
    ];
}

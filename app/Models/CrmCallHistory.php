<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmCallHistory extends Model
{
    protected $table = 'crm_call_history';

    public $timestamps = true;

    protected $fillable = [
        'lead_id',
        'phone_number',
        'campaign_code',
        'form_type',
        'record_id',
        'agent',
        'status',
        'remarks',
    ];
}

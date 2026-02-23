<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormField extends Model
{
    protected $fillable = [
        'campaign_code',
        'form_type',
        'field_name',
        'field_label',
        'field_type',
        'is_required',
        'field_order',
        'options',
        'vici_params',
        'field_width',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }
}

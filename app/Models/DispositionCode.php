<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispositionCode extends Model
{
    protected $fillable = [
        'campaign_code',
        'code',
        'label',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardLayout extends Model
{
    protected $fillable = [
        'campaign_code',
        'layout',
    ];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
        ];
    }
}

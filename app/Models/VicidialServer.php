<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VicidialServer extends Model
{
    protected $fillable = [
        'campaign_code',
        'server_name',
        'api_url',
        'db_host',
        'db_username',
        'db_password',
        'db_name',
        'db_port',
        'api_user',
        'api_pass',
        'source',
        'is_active',
        'is_default',
        'priority',
    ];

    protected $hidden = [
        'db_password',
        'api_pass',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailSmtpSetting extends Model
{
    protected $fillable = [
        'enabled',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_name',
        'from_address',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
        ];
    }
}

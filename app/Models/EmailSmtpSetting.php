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
        'provider',
        'rate_per_minute',
        'postmark_webhook_user',
        'postmark_webhook_password',
        'postmark_server_id',
        'postmark_message_stream',
    ];

    protected $hidden = ['password', 'postmark_webhook_password'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port' => 'integer',
            'password' => 'encrypted',
            'postmark_webhook_password' => 'encrypted',
            'rate_per_minute' => 'integer',
        ];
    }
}

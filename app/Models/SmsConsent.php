<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsConsent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}

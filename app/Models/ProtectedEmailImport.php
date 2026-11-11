<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProtectedEmailImport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload', 'lease_token'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'lease_expires_at' => 'datetime', 'heartbeat_at' => 'datetime'];
    }
}

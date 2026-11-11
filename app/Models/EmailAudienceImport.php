<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailAudienceImport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['storage_path', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'heartbeat_at' => 'datetime'];
    }
}

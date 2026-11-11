<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTrackingToken extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['token_hash', 'destination'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}

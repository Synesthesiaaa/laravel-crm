<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsGateway extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return ['password' => 'encrypted', 'enabled' => 'boolean', 'inbound_verified' => 'boolean', 'verified_at' => 'datetime', 'last_contact_at' => 'datetime', 'reserved_until' => 'datetime'];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(SmsChannel::class);
    }
}

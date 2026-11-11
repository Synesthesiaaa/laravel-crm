<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailProviderEvent extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'processed_at' => 'datetime'];
    }
}

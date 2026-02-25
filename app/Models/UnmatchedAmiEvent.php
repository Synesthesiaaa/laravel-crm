<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnmatchedAmiEvent extends Model
{
    protected $table = 'unmatched_ami_events';

    protected $fillable = [
        'event',
        'linkedid',
        'channel',
        'extracted_extension',
        'payload',
        'processed',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed' => 'boolean',
            'received_at' => 'datetime',
        ];
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }
}

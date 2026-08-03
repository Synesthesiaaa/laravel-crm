<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataRetentionPolicy extends Model
{
    protected $fillable = [
        'form_id',
        'cutoff_date',
        'deletion_mode',
        'selected_fields',
        'is_active',
        'last_run_at',
        'last_deleted_count',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_date' => 'date',
            'selected_fields' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
            'last_deleted_count' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}

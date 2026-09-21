<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DataRetentionPolicy extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'form_id',
                'from_date',
                'to_date',
                'deletion_mode',
                'selected_fields',
                'is_active',
                'run_mode',
                'run_at',
                'recurrence',
                'run_time',
                'run_day_of_week',
                'run_day_of_month',
                'next_run_at',
                'last_run_at',
                'last_deleted_count',
                'last_run_status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'form_id',
        'from_date',
        'to_date',
        'deletion_mode',
        'selected_fields',
        'is_active',
        'run_mode',
        'run_at',
        'recurrence',
        'run_time',
        'run_day_of_week',
        'run_day_of_month',
        'next_run_at',
        'last_run_at',
        'last_deleted_count',
        'last_run_status',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'selected_fields' => 'array',
            'is_active' => 'boolean',
            'run_at' => 'datetime',
            'run_day_of_week' => 'integer',
            'run_day_of_month' => 'integer',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
            'last_deleted_count' => 'integer',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }
}

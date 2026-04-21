<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lead extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $table = 'leads';

    protected $fillable = [
        'list_id',
        'campaign_code',
        'vendor_lead_code',
        'source_id',
        'phone_code',
        'phone_number',
        'alt_phone',
        'title',
        'first_name',
        'middle_initial',
        'last_name',
        'address1',
        'address2',
        'address3',
        'city',
        'state',
        'province',
        'postal_code',
        'country',
        'gender',
        'date_of_birth',
        'email',
        'security_phrase',
        'comments',
        'status',
        'enabled',
        'called_count',
        'last_called_at',
        'last_local_call_time',
        'user',
        'custom_fields',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'called_count' => 'integer',
            'last_called_at' => 'datetime',
            'last_local_call_time' => 'datetime',
            'date_of_birth' => 'date',
            'custom_fields' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'list_id', 'campaign_code', 'phone_number', 'first_name', 'last_name',
                'status', 'enabled', 'called_count',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(LeadList::class, 'list_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_code', 'code');
    }

    public function scopeForList(Builder $query, int $listId): Builder
    {
        return $query->where('list_id', $listId);
    }

    public function scopeForCampaign(Builder $query, string $campaignCode): Builder
    {
        return $query->where('campaign_code', $campaignCode);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeDialable(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->whereNotIn('status', ['DNC', 'INACTIVE', 'COMPLETED']);
    }

    public function displayName(): string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);

        return $parts === [] ? ($this->phone_number ?? '') : trim(implode(' ', $parts));
    }
}

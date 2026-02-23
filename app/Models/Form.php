<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    protected $fillable = [
        'campaign_code',
        'form_code',
        'name',
        'table_name',
        'color',
        'icon',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_code', 'code');
    }

    public function formFields(): HasMany
    {
        return $this->hasMany(FormField::class, 'form_type', 'form_code')
            ->whereColumn('form_fields.campaign_code', 'forms.campaign_code');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormField extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'campaign_code',
        'form_type',
        'field_name',
        'field_label',
        'field_type',
        'is_required',
        'field_order',
        'options',
        'vici_params',
        'field_width',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
        ];
    }

    /**
     * Normalized list of option values for select / multiselect (from JSON in `options`).
     *
     * @return list<string>
     */
    public function optionValues(): array
    {
        $raw = $this->options;
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
        if (! is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $item) {
            if (is_string($item) || is_numeric($item)) {
                $out[] = (string) $item;
            } elseif (is_array($item)) {
                $v = $item['value'] ?? $item['label'] ?? null;
                if ($v !== null && $v !== '') {
                    $out[] = (string) $v;
                }
            }
        }

        return array_values(array_unique($out));
    }

    /** One option per line for admin textarea. */
    public function optionsTextForAdmin(): string
    {
        return implode("\n", $this->optionValues());
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_code', 'code');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class, 'form_type', 'form_code');
    }

    public function scopeForCampaign(Builder $query, string $campaignCode): Builder
    {
        return $query->where('campaign_code', $campaignCode);
    }

    public function scopeForForm(Builder $query, string $formType): Builder
    {
        return $query->where('form_type', $formType);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('field_order')->orderBy('id');
    }
}

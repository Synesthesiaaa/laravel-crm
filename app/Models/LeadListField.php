<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadListField extends Model
{
    use HasFactory;

    protected $table = 'lead_list_fields';

    protected $fillable = [
        'campaign_code',
        'field_key',
        'field_label',
        'field_type',
        'field_options',
        'is_standard',
        'visible',
        'exportable',
        'importable',
        'field_order',
    ];

    protected function casts(): array
    {
        return [
            'field_options' => 'array',
            'is_standard' => 'boolean',
            'visible' => 'boolean',
            'exportable' => 'boolean',
            'importable' => 'boolean',
            'field_order' => 'integer',
        ];
    }

    public function scopeForCampaign(Builder $query, string $campaignCode): Builder
    {
        return $query->where('campaign_code', $campaignCode);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    public function scopeExportable(Builder $query): Builder
    {
        return $query->where('exportable', true);
    }

    public function scopeImportable(Builder $query): Builder
    {
        return $query->where('importable', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('field_order')->orderBy('id');
    }

    /**
     * Default ViciDial-parity fields seeded on first use.
     *
     * @return array<int, array{key: string, label: string, type: string, order: int}>
     */
    public static function standardFields(): array
    {
        return [
            ['key' => 'vendor_lead_code', 'label' => 'Vendor Lead Code', 'type' => 'text', 'order' => 10],
            ['key' => 'source_id', 'label' => 'Source ID', 'type' => 'text', 'order' => 20],
            ['key' => 'phone_code', 'label' => 'Phone Code', 'type' => 'text', 'order' => 30],
            ['key' => 'phone_number', 'label' => 'Phone Number', 'type' => 'text', 'order' => 40],
            ['key' => 'alt_phone', 'label' => 'Alt Phone', 'type' => 'text', 'order' => 50],
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'order' => 60],
            ['key' => 'first_name', 'label' => 'First Name', 'type' => 'text', 'order' => 70],
            ['key' => 'middle_initial', 'label' => 'MI', 'type' => 'text', 'order' => 80],
            ['key' => 'last_name', 'label' => 'Last Name', 'type' => 'text', 'order' => 90],
            ['key' => 'address1', 'label' => 'Address 1', 'type' => 'text', 'order' => 100],
            ['key' => 'address2', 'label' => 'Address 2', 'type' => 'text', 'order' => 110],
            ['key' => 'address3', 'label' => 'Address 3', 'type' => 'text', 'order' => 120],
            ['key' => 'city', 'label' => 'City', 'type' => 'text', 'order' => 130],
            ['key' => 'state', 'label' => 'State', 'type' => 'text', 'order' => 140],
            ['key' => 'province', 'label' => 'Province', 'type' => 'text', 'order' => 150],
            ['key' => 'postal_code', 'label' => 'Postal Code', 'type' => 'text', 'order' => 160],
            ['key' => 'country', 'label' => 'Country', 'type' => 'text', 'order' => 170],
            ['key' => 'gender', 'label' => 'Gender', 'type' => 'text', 'order' => 180],
            ['key' => 'date_of_birth', 'label' => 'Date of Birth', 'type' => 'date', 'order' => 190],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'order' => 200],
            ['key' => 'security_phrase', 'label' => 'Security Phrase', 'type' => 'text', 'order' => 210],
            ['key' => 'comments', 'label' => 'Comments', 'type' => 'textarea', 'order' => 220],
            ['key' => 'status', 'label' => 'Status', 'type' => 'text', 'order' => 230],
            ['key' => 'called_count', 'label' => 'Called Count', 'type' => 'number', 'order' => 240],
            ['key' => 'last_called_at', 'label' => 'Last Called At', 'type' => 'date', 'order' => 250],
            ['key' => 'last_local_call_time', 'label' => 'Last Local Call Time', 'type' => 'date', 'order' => 260],
            ['key' => 'user', 'label' => 'User', 'type' => 'text', 'order' => 270],
        ];
    }
}

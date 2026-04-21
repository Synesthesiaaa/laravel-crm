<?php

namespace App\Services\Leads;

use App\Models\LeadListField;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LeadFieldService
{
    /**
     * Ensure ViciDial-parity standard fields exist for this campaign; seed missing ones.
     */
    public function ensureStandardFields(string $campaignCode): void
    {
        $existing = LeadListField::forCampaign($campaignCode)
            ->pluck('field_key')
            ->all();

        foreach (LeadListField::standardFields() as $field) {
            if (in_array($field['key'], $existing, true)) {
                continue;
            }

            LeadListField::create([
                'campaign_code' => $campaignCode,
                'field_key' => $field['key'],
                'field_label' => $field['label'],
                'field_type' => $field['type'],
                'is_standard' => true,
                'visible' => true,
                'exportable' => true,
                'importable' => true,
                'field_order' => $field['order'],
            ]);
        }
    }

    /**
     * @return Collection<int, LeadListField>
     */
    public function getFields(string $campaignCode, bool $onlyVisible = false): Collection
    {
        $this->ensureStandardFields($campaignCode);

        $query = LeadListField::forCampaign($campaignCode)->ordered();
        if ($onlyVisible) {
            $query->visible();
        }

        return $query->get();
    }

    /**
     * Build the column layout for the leads list admin table.
     *
     * @return array{columns: list<string>, headers: array<string, string>}
     */
    public function getColumnLayout(string $campaignCode): array
    {
        $fields = $this->getFields($campaignCode, true);
        $columns = ['id'];
        $headers = ['id' => 'ID'];

        foreach ($fields as $field) {
            $columns[] = $field->field_key;
            $headers[$field->field_key] = $field->field_label;
        }

        return ['columns' => $columns, 'headers' => $headers];
    }

    /**
     * Standard Lead model columns vs custom fields.
     *
     * @return list<string>
     */
    public function standardColumns(): array
    {
        return array_map(fn ($f) => $f['key'], LeadListField::standardFields());
    }

    /**
     * Normalize a field key (slug-safe).
     */
    public function normalizeKey(string $value): string
    {
        return Str::slug(Str::snake(strtolower(trim($value))), '_');
    }
}

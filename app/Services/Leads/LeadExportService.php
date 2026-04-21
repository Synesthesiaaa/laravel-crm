<?php

namespace App\Services\Leads;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

class LeadExportService
{
    public function __construct(
        protected LeadFieldService $fieldService,
    ) {}

    /**
     * Build a filtered query for export.
     *
     * @param  array<string, mixed>  $filters
     */
    public function query(string $campaignCode, array $filters = []): Builder
    {
        $query = Lead::query()->forCampaign($campaignCode);

        if (! empty($filters['list_id'])) {
            $query->where('list_id', (int) $filters['list_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (isset($filters['enabled'])) {
            $query->where('enabled', (bool) $filters['enabled']);
        }
        if (! empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        if (isset($filters['min_called_count'])) {
            $query->where('called_count', '>=', (int) $filters['min_called_count']);
        }

        return $query->orderBy('id');
    }

    /**
     * Build export columns + headers from lead_list_fields (exportable only).
     *
     * @return array{columns: list<string>, headers: list<string>}
     */
    public function buildColumns(string $campaignCode): array
    {
        $fields = $this->fieldService->getFields($campaignCode);
        $columns = ['id'];
        $headers = ['ID'];

        foreach ($fields as $field) {
            if (! $field->exportable) {
                continue;
            }
            $columns[] = $field->field_key;
            $headers[] = $field->field_label;
        }

        return compact('columns', 'headers');
    }

    /**
     * Extract a value for one export column from a Lead (supporting custom_fields).
     */
    public function valueFor(Lead $lead, string $column): mixed
    {
        if (array_key_exists($column, $lead->getAttributes())) {
            return $lead->getAttribute($column);
        }
        $custom = $lead->custom_fields ?? [];

        return is_array($custom) ? ($custom[$column] ?? null) : null;
    }
}

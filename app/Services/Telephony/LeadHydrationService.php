<?php

namespace App\Services\Telephony;

use App\Models\AgentScreenField;
use App\Models\User;

class LeadHydrationService
{
    public function __construct(
        protected LeadService $leadService,
        protected TelephonyLogger $telephonyLogger,
    ) {}

    /**
     * Fetch and map Vicidial lead data for Agent Screen autofill.
     *
     * @return array{
     *   lead_id:?string,
     *   phone_number:?string,
     *   client_name:?string,
     *   capture_data:array<string,string>,
     *   raw_fields:array<string,string>
     * }
     */
    public function hydrate(User $user, string $campaign, ?int $leadId = null, ?string $phoneNumber = null): array
    {
        $payload = [
            'lead_id' => $leadId ? (string) $leadId : null,
            'phone_number' => $phoneNumber ?: null,
            'client_name' => null,
            'capture_data' => [],
            'raw_fields' => [],
        ];

        if (! $leadId && ! $phoneNumber) {
            return $payload;
        }

        $result = $this->leadService->allInfo($user, $campaign, $leadId, $phoneNumber);
        if (! $result->success) {
            $this->telephonyLogger->warning('LeadHydrationService', 'Unable to fetch lead_all_info for autofill', [
                'campaign' => $campaign,
                'lead_id' => $leadId,
                'phone_number' => $phoneNumber,
                'error' => $result->message,
            ]);

            return $payload;
        }

        $rows = (array) data_get($result->data, 'rows', []);
        $rawFields = $this->parseRowsToAssoc($rows);
        if ($rawFields === []) {
            return $payload;
        }

        $payload['raw_fields'] = $rawFields;
        $payload['lead_id'] = $this->firstNonEmpty([
            $payload['lead_id'],
            $rawFields['lead_id'] ?? null,
        ]);
        $payload['phone_number'] = $this->firstNonEmpty([
            $payload['phone_number'],
            $rawFields['phone_number'] ?? null,
        ]);
        $payload['client_name'] = $this->resolveClientName($rawFields);
        $payload['capture_data'] = $this->mapCaptureData($campaign, $rawFields);

        return $payload;
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @return array<string, string>
     */
    protected function parseRowsToAssoc(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && $row !== []));
        if ($rows === []) {
            return [];
        }

        if ($this->looksLikeKeyValueRows($rows)) {
            $assoc = [];
            foreach ($rows as $row) {
                $key = $this->normalizeFieldName((string) ($row[0] ?? ''));
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = trim((string) ($row[1] ?? ''));
            }

            return $assoc;
        }

        if (count($rows) >= 2 && $this->looksLikeHeaderRow($rows[0])) {
            $headers = array_map(fn ($value) => $this->normalizeFieldName((string) $value), $rows[0]);
            $values = $rows[1];
            $assoc = [];

            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = trim((string) ($values[$index] ?? ''));
            }

            return $assoc;
        }

        return [];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    protected function looksLikeKeyValueRows(array $rows): bool
    {
        foreach ($rows as $row) {
            if (count($row) !== 2) {
                return false;
            }
            if ($this->normalizeFieldName((string) ($row[0] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $header
     */
    protected function looksLikeHeaderRow(array $header): bool
    {
        $valid = 0;
        foreach ($header as $column) {
            if ($this->normalizeFieldName((string) $column) !== '') {
                $valid++;
            }
        }

        return $valid >= 3;
    }

    /**
     * @param  array<string, string>  $rawFields
     * @return array<string, string>
     */
    protected function mapCaptureData(string $campaign, array $rawFields): array
    {
        $captureData = [];
        $normalized = [];
        foreach ($rawFields as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }

        $aliasIndex = $this->buildAliasIndex((array) config('vicidial_fields.aliases', []));
        $mappings = AgentScreenField::query()
            ->forCampaign($campaign)
            ->where(function ($query) {
                $query->whereNull('direction')
                    ->orWhereIn('direction', ['get', 'both']);
            })
            ->get(['field_key', 'vici_field', 'direction']);

        foreach ($mappings as $mapping) {
            $viciField = $this->resolveViciKey(
                (string) $mapping->field_key,
                $mapping->vici_field !== null ? (string) $mapping->vici_field : null,
                $aliasIndex
            );
            if ($viciField === null || ! array_key_exists($viciField, $normalized)) {
                continue;
            }

            $captureData[$mapping->field_key] = (string) $normalized[$viciField];
        }

        return $captureData;
    }

    /**
     * @param  array<string, mixed>  $aliases
     * @return array<string, string>
     */
    protected function buildAliasIndex(array $aliases): array
    {
        $aliasIndex = [];

        foreach ($aliases as $canonical => $variants) {
            $canonicalKey = $this->normalizeFieldName((string) $canonical);
            if ($canonicalKey === '') {
                continue;
            }

            $aliasIndex[$canonicalKey] = $canonicalKey;
            if (! is_array($variants)) {
                continue;
            }

            foreach ($variants as $variant) {
                $variantKey = $this->normalizeFieldName((string) $variant);
                if ($variantKey === '') {
                    continue;
                }

                $aliasIndex[$variantKey] = $canonicalKey;
            }
        }

        return $aliasIndex;
    }

    /**
     * @param  array<string, string>  $aliasIndex
     */
    protected function resolveViciKey(string $fieldKey, ?string $viciField, array $aliasIndex): ?string
    {
        $explicit = $this->normalizeFieldName((string) $viciField);
        if ($explicit !== '') {
            return $explicit;
        }

        $normalizedFieldKey = $this->normalizeFieldName($fieldKey);
        if ($normalizedFieldKey === '') {
            return null;
        }

        if (isset($aliasIndex[$normalizedFieldKey])) {
            return $aliasIndex[$normalizedFieldKey];
        }

        $strippedFieldKey = preg_replace('/^(customer_|cust_|lead_)+/', '', $normalizedFieldKey) ?: '';
        if ($strippedFieldKey !== '' && isset($aliasIndex[$strippedFieldKey])) {
            return $aliasIndex[$strippedFieldKey];
        }

        return $normalizedFieldKey;
    }

    /**
     * @param  array<string, string>  $rawFields
     */
    protected function resolveClientName(array $rawFields): ?string
    {
        $first = trim((string) ($rawFields['first_name'] ?? ''));
        $last = trim((string) ($rawFields['last_name'] ?? ''));
        $full = trim($first.' '.$last);

        if ($full !== '') {
            return $full;
        }

        $fallback = trim((string) ($rawFields['full_name'] ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    /**
     * @param  array<int, ?string>  $values
     */
    protected function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    protected function normalizeFieldName(string $field): string
    {
        $field = strtolower(trim($field));
        if ($field === '') {
            return '';
        }

        return preg_match('/^[a-z0-9_]+$/', $field) ? $field : '';
    }
}

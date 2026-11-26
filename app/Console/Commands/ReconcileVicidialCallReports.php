<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use JsonException;

class ReconcileVicidialCallReports extends Command
{
    protected $signature = 'reports:reconcile-vicidial {crm : Captured CRM dashboard JSON} {native : Normalized native VICIdial JSON}';

    protected $description = 'Compare a closed CRM call report with an independently captured VICIdial report';

    public function handle(): int
    {
        try {
            $crm = $this->readSnapshot((string) $this->argument('crm'));
            $native = $this->readSnapshot((string) $this->argument('native'));
            if (! is_string($native['export_reference'] ?? null) || blank($native['export_reference'])) {
                throw new InvalidArgumentException('The native snapshot requires an export_reference.');
            }
            if (($crm['report_scope']['days_open'] ?? true) !== false
                || ($native['report_scope']['days_open'] ?? true) !== false) {
                throw new InvalidArgumentException('Exact reconciliation requires a closed reporting window.');
            }
            $crmScope = $this->scope($crm);
            if ($crmScope !== $this->scope($native)) {
                throw new InvalidArgumentException('The snapshots have different server, campaign, date, timezone, in-group, or disposition scope.');
            }
            $actual = $this->counts($crm, $crmScope['campaigns']);
            $expected = $this->counts($native, $crmScope['campaigns']);
            $rate = $actual['attempts'] > 0 ? round(100 * $actual['answered'] / $actual['attempts'], 2) : 0.0;
            if (! is_numeric($crm['summary']['answer_rate'] ?? null)
                || (float) $crm['summary']['answer_rate'] !== $rate) {
                throw new InvalidArgumentException('The CRM answer rate does not match its reported counts.');
            }
        } catch (InvalidArgumentException|JsonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $differences = [];
        foreach (array_keys($expected + $actual) as $metric) {
            if (($expected[$metric] ?? null) !== ($actual[$metric] ?? null)) {
                $differences[] = [$metric, $expected[$metric] ?? 'missing', $actual[$metric] ?? 'missing'];
            }
        }
        if ($differences !== []) {
            $this->table(['Metric', 'VICIdial', 'CRM'], $differences);

            return self::FAILURE;
        }

        $this->info('VICIdial and CRM counts match for the captured reporting scope.');

        return self::SUCCESS;
    }

    private function readSnapshot(string $path): array
    {
        $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new InvalidArgumentException('Both snapshots must be readable local JSON files.');
        }
        $snapshot = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        $snapshot = $snapshot['data'] ?? $snapshot;
        if (! is_array($snapshot)) {
            throw new InvalidArgumentException('The snapshot must contain a report object.');
        }

        return $snapshot;
    }

    private function scope(array $snapshot): array
    {
        $validator = Validator::make($snapshot, [
            'campaign_scope.server.id' => ['required', 'integer', 'min:1'],
            'filters.crm_campaign' => ['required', 'string'],
            'filters.campaigns' => ['required', 'string'],
            'filters.query_date' => ['required', 'date_format:Y-m-d'],
            'filters.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:filters.query_date'],
            'filters.timezone' => ['required', 'timezone'],
            'filters.disposition_scope' => ['required', 'in:all,exclude_system,system_only'],
            'filters.ingroups' => ['present', 'nullable', 'string'],
        ]);
        if ($validator->fails()) {
            throw new InvalidArgumentException($validator->errors()->first());
        }
        $filters = $snapshot['filters'];
        $campaigns = array_unique(array_map('strtoupper', array_map('trim', explode('|', $filters['campaigns']))));
        sort($campaigns);
        if (in_array('', $campaigns, true) || in_array('---ALL---', $campaigns, true)) {
            throw new InvalidArgumentException('Each snapshot must list the effective VICIdial campaign codes.');
        }

        return [
            'server_id' => (int) $snapshot['campaign_scope']['server']['id'],
            'crm_campaign' => strtolower($filters['crm_campaign']),
            'campaigns' => $campaigns,
            'query_date' => $filters['query_date'],
            'end_date' => $filters['end_date'],
            'timezone' => $filters['timezone'],
            'disposition_scope' => $filters['disposition_scope'],
            'ingroups' => $filters['ingroups'],
        ];
    }

    private function counts(array $snapshot, array $campaigns): array
    {
        if (! is_array($snapshot['campaigns'] ?? null) || ! is_array($snapshot['disposition_rows'] ?? null)) {
            throw new InvalidArgumentException('The snapshot requires campaign totals and disposition rows.');
        }
        $counts = [
            'attempts' => $this->countAt($snapshot, 'summary.total_calls'),
            'answered' => $this->countAt($snapshot, 'summary.answered_calls'),
            'disposition subtotal' => $this->countAt($snapshot, 'disposition_summary.total_calls'),
        ];
        $seen = [];
        $attempts = 0;
        $answered = 0;
        foreach ($snapshot['campaigns'] as $row) {
            $campaign = $this->campaign($row, $campaigns);
            if (isset($seen[$campaign])) {
                throw new InvalidArgumentException('A snapshot contains multiple call-total rows for the same campaign.');
            }
            $seen[$campaign] = true;
            $attempts += $counts[$campaign.' attempts'] = $this->countAt($row, 'total_calls');
            $answered += $counts[$campaign.' answered'] = $this->countAt($row, 'answered_calls');
            if ($counts[$campaign.' answered'] > $counts[$campaign.' attempts']) {
                throw new InvalidArgumentException('A campaign has more answered calls than attempts.');
            }
        }
        if (array_diff($campaigns, array_keys($seen)) !== []
            || $attempts !== $counts['attempts'] || $answered !== $counts['answered']) {
            throw new InvalidArgumentException('Campaign call counts are incomplete or do not add up to the headline totals.');
        }
        $dispositionTotal = 0;
        foreach ($snapshot['disposition_rows'] as $row) {
            $campaign = $this->campaign($row, $campaigns);
            if (! is_array($row['metrics'] ?? null)) {
                throw new InvalidArgumentException('A disposition row has no metrics array.');
            }
            foreach ($row['metrics'] as $metric) {
                $value = $this->countAt($metric, 'value');
                $label = strtoupper(trim((string) ($metric['label'] ?? '')));
                if (! preg_match('/^[A-Z0-9_-]+$/D', $label)) {
                    throw new InvalidArgumentException('A disposition row has an invalid code.');
                }
                if ($value > 0) {
                    $key = $campaign.' disposition '.$label;
                    $counts[$key] = ($counts[$key] ?? 0) + $value;
                    $dispositionTotal += $value;
                }
            }
        }
        if ($dispositionTotal !== $counts['disposition subtotal']) {
            throw new InvalidArgumentException('Disposition counts are incomplete or do not add up to the subtotal.');
        }

        return $counts;
    }

    private function countAt(mixed $snapshot, string $path): int
    {
        $value = data_get($snapshot, $path);
        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("Missing or invalid integer count at {$path}.");
        }

        return $value;
    }

    private function campaign(mixed $row, array $campaigns): string
    {
        $value = is_array($row) ? ($row['campaign'] ?? null) : null;
        $campaign = is_string($value) ? strtoupper(trim($value)) : '';
        if (! in_array($campaign, $campaigns, true)) {
            throw new InvalidArgumentException('A row contains a VICIdial campaign outside the captured scope.');
        }

        return $campaign;
    }
}

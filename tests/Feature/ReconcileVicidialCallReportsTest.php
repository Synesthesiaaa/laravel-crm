<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReconcileVicidialCallReportsTest extends TestCase
{
    public function test_matches_a_native_snapshot_and_identifies_redistributed_disposition_counts(): void
    {
        $snapshot = [
            'export_reference' => 'Operations export 2026-11-25',
            'campaign_scope' => ['server' => ['id' => 42]],
            'filters' => [
                'crm_campaign' => 'mbsales',
                'campaigns' => 'MBSALES|MBSALES2',
                'query_date' => '2026-11-25',
                'end_date' => '2026-11-25',
                'timezone' => 'Asia/Manila',
                'ingroups' => null,
                'disposition_scope' => 'all',
            ],
            'report_scope' => ['days_open' => false],
            'summary' => ['total_calls' => 10, 'answered_calls' => 3, 'answer_rate' => 30],
            'campaigns' => [
                ['campaign' => 'MBSALES', 'total_calls' => 5, 'answered_calls' => 3],
                ['campaign' => 'MBSALES2', 'total_calls' => 5, 'answered_calls' => 0],
            ],
            'disposition_summary' => ['total_calls' => 10],
            'disposition_rows' => [
                ['campaign' => 'MBSALES', 'metrics' => [['label' => 'SALE', 'value' => 3], ['label' => 'NA', 'value' => 2]]],
                ['campaign' => 'MBSALES2', 'metrics' => [['label' => 'NA', 'value' => 5]]],
            ],
        ];
        $crmFile = tempnam(sys_get_temp_dir(), 'crm-report-');
        $nativeFile = tempnam(sys_get_temp_dir(), 'native-report-');
        $arguments = ['crm' => $crmFile, 'native' => $nativeFile];

        try {
            file_put_contents($crmFile, json_encode(['data' => $snapshot], JSON_THROW_ON_ERROR));
            file_put_contents($nativeFile, json_encode($snapshot, JSON_THROW_ON_ERROR));
            $this->artisan('reports:reconcile-vicidial', $arguments)
                ->expectsOutput('VICIdial and CRM counts match for the captured reporting scope.')
                ->assertSuccessful();

            $snapshot['disposition_rows'][0]['metrics'][1]['value'] = 1;
            $snapshot['disposition_rows'][1]['metrics'][0]['value'] = 6;
            file_put_contents($nativeFile, json_encode($snapshot, JSON_THROW_ON_ERROR));
            $this->artisan('reports:reconcile-vicidial', $arguments)
                ->expectsTable(['Metric', 'VICIdial', 'CRM'], [
                    ['MBSALES disposition NA', 1, 2],
                    ['MBSALES2 disposition NA', 6, 5],
                ])
                ->assertFailed();

            $snapshot['filters']['timezone'] = 'UTC';
            file_put_contents($nativeFile, json_encode($snapshot, JSON_THROW_ON_ERROR));
            $this->artisan('reports:reconcile-vicidial', $arguments)
                ->expectsOutput('The snapshots have different server, campaign, date, timezone, in-group, or disposition scope.')
                ->assertFailed();

            $snapshot['report_scope']['days_open'] = true;
            file_put_contents($nativeFile, json_encode($snapshot, JSON_THROW_ON_ERROR));
            $this->artisan('reports:reconcile-vicidial', $arguments)
                ->expectsOutput('Exact reconciliation requires a closed reporting window.')
                ->assertFailed();
        } finally {
            unlink($crmFile);
            unlink($nativeFile);
        }
    }
}

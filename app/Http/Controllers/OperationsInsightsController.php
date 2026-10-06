<?php

namespace App\Http\Controllers;

use App\Services\CampaignService;
use App\Services\DashboardLayoutService;
use App\Services\DashboardSalesRangeService;
use App\Services\DashboardStatsService;
use App\Services\DataMasterService;
use App\Services\Telephony\HistoricalTelephonyReportService;
use App\Services\Telephony\RealtimeTelephonyReportService;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OperationsInsightsController extends Controller
{
    private const MAX_HISTORICAL_RANGE_DAYS = 31;

    public function __construct(
        protected CampaignService $campaignService,
        protected DataMasterService $dataMasterService,
        protected DashboardStatsService $dashboardStatsService,
        protected DashboardLayoutService $dashboardLayoutService,
        protected DashboardSalesRangeService $dashboardSalesRangeService,
    ) {}

    public function index(Request $request)
    {
        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaign($request, $campaigns);

        return view('operations-insights', [
            'campaigns' => collect($campaigns)->map(
                fn (array $config, string $code): array => [
                    'code' => $code,
                    'name' => (string) ($config['name'] ?? $code),
                ],
            )->values()->all(),
            'selectedCampaign' => $campaign,
        ]);
    }

    public function data(
        Request $request,
        HistoricalTelephonyReportService $historicalReports,
        RealtimeTelephonyReportService $realtimeReports,
    ): JsonResponse {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:100'],
            'mode' => ['nullable', 'in:historical,live,today'],
            'campaigns' => ['nullable', 'string', 'max:255'],
            'ingroups' => ['nullable', 'string', 'max:255'],
            'query_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'timezone' => ['nullable', 'timezone'],
            'disposition_scope' => ['nullable', 'in:all,exclude_system,system_only'],
            'comparison' => ['nullable', 'in:none,previous_period,previous_day,previous_week,previous_month'],
            'sales_date' => ['nullable', 'date_format:Y-m-d'],
            'sales_start' => ['nullable', 'date_format:H:i'],
            'sales_end' => ['nullable', 'date_format:H:i'],
        ]);

        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaign($request, $campaigns);
        $campaignConfig = $campaigns[$campaign] ?? [];
        $mode = (string) ($validated['mode'] ?? 'historical');
        if ($mode === 'historical') {
            $this->validateHistoricalRange($validated);
        }
        $salesRange = $this->dashboardSalesRangeService->resolve($request);
        $layout = $this->dashboardLayoutService->getForCampaign($campaign);

        $dashboard = [
            'kpis' => $this->dashboardStatsService->getSalesKpisForCampaign(
                $campaign,
                $salesRange['from'],
                $salesRange['until'],
            ),
            'summary' => $this->dashboardStatsService->getDashboardSummaryForCampaign($campaign),
            'activity' => [
                'last_24_hours' => $this->dashboardStatsService->getLast24HourActivityTrend($campaign),
                'weekly' => $this->dashboardStatsService->getWeeklyActivityTrend($campaign),
                'monthly' => $this->dashboardStatsService->getMonthlyActivityTrend($campaign),
            ],
            'campaign_report' => $this->dashboardStatsService->getDailyCampaignReport(
                $campaign,
                Carbon::parse($salesRange['date'], config('app.timezone')),
            ),
            'sales_range' => [
                'date' => $salesRange['date'],
                'start' => $salesRange['start'],
                'end' => $salesRange['end'],
            ],
            'layout' => [
                'sections' => $layout['sections'] ?? [],
                'amounts' => $layout['amounts'] ?? [],
            ],
        ];

        if (! (bool) data_get($layout, 'amounts.enabled', true)) {
            $dashboard = $this->withoutDashboardAmounts($dashboard);
        }

        if ($mode === 'historical') {
            $reports = $historicalReports->dashboard(null, $campaign, $validated);
            unset($reports['campaign_scope']);
            $reports['availability'] = $this->publicAvailability($reports['availability'] ?? []);
            if (is_array(data_get($reports, 'comparison.availability'))) {
                $reports['comparison']['availability'] = $this->publicAvailability($reports['comparison']['availability']);
            }
            $reports = ['mode' => 'historical', ...$reports];
        } else {
            $reports = $this->publicRealtimeReport($realtimeReports->dashboard($request, $mode));
        }

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'context' => [
                    'campaign' => [
                        'code' => $campaign,
                        'name' => (string) ($campaignConfig['name'] ?? $campaign),
                    ],
                    'generated_at' => now()->toIso8601String(),
                ],
                'dashboard' => $dashboard,
                'reports' => $reports,
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function records(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign' => ['nullable', 'string', 'max:100'],
            'form' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $campaigns = $this->campaignService->getCampaigns();
        $campaign = $this->resolveCampaign($request, $campaigns);
        $campaignConfig = $campaigns[$campaign] ?? [];
        $forms = is_array($campaignConfig['forms'] ?? null) ? $campaignConfig['forms'] : [];
        $requestedForm = trim((string) ($validated['form'] ?? ''));

        if ($requestedForm !== '' && ! isset($forms[$requestedForm])) {
            throw ValidationException::withMessages([
                'form' => 'The selected form is not available for this campaign.',
            ]);
        }

        $form = $requestedForm !== '' ? $requestedForm : (string) array_key_first($forms);
        if ($form === '' || ! isset($forms[$form])) {
            return response()->json([
                'success' => true,
                'message' => null,
                'data' => [
                    'forms' => [],
                    'selected_form' => null,
                    'columns' => [],
                    'records' => [],
                    'pagination' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                    ],
                ],
            ])->header('Cache-Control', 'private, no-store');
        }

        $formConfig = $forms[$form];
        $tableName = (string) ($formConfig['table_name'] ?? $formConfig['table'] ?? '');
        $allowedTables = $this->dataMasterService->getAllowedTables($campaignConfig);
        $search = trim((string) ($validated['search'] ?? ''));
        $records = $this->dataMasterService->getRecords(
            $tableName,
            $allowedTables,
            search: $search !== '' ? $search : null,
        );

        $first = $records->first();
        $available = $first ? array_keys((array) $first) : null;
        $layout = $this->dataMasterService->getColumnLayout($campaign, $form, $available);
        $percentageColumns = $this->dataMasterService->getPercentageColumns($campaign, $form);
        $columns = collect($layout['columns'] ?? [])
            ->map(fn (string $column): array => [
                'key' => $column,
                'label' => (string) (($layout['headers'] ?? [])[$column] ?? $column),
            ])
            ->values()
            ->all();
        $rows = collect($records->items())
            ->map(function (mixed $record) use ($columns, $percentageColumns): array {
                $record = $this->publicArray($record);

                return collect($columns)
                    ->mapWithKeys(function (array $column) use ($record, $percentageColumns): array {
                        $key = $column['key'];

                        return [$key => $this->dataMasterService->formatValue(
                            $key,
                            $record[$key] ?? null,
                            $percentageColumns,
                        )];
                    })
                    ->all();
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => null,
            'data' => [
                'forms' => collect($forms)
                    ->map(fn (array $config, string $code): array => [
                        'code' => $code,
                        'name' => (string) ($config['name'] ?? $code),
                    ])
                    ->values()
                    ->all(),
                'selected_form' => [
                    'code' => $form,
                    'name' => (string) ($formConfig['name'] ?? $form),
                ],
                'columns' => $columns,
                'records' => $rows,
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'last_page' => $records->lastPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    /** @param array<string, array<string, mixed>> $campaigns */
    private function resolveCampaign(Request $request, array $campaigns): string
    {
        $requested = trim((string) $request->query('campaign', ''));
        if ($requested !== '' && isset($campaigns[$requested])) {
            return $requested;
        }

        $campaign = (string) array_key_first($campaigns);
        abort_if($campaign === '', 404, 'No active campaigns are available.');

        return $campaign;
    }

    /** @param array<string, mixed> $availability */
    private function publicAvailability(array $availability): array
    {
        return [
            'status' => (string) ($availability['status'] ?? 'unavailable'),
            'available_sections' => $availability['available_sections'] ?? null,
            'failed_sections' => $availability['failed_sections'] ?? null,
            'message' => $availability['message'] ?? null,
            'sources' => collect($availability['sources'] ?? [])
                ->map(fn (mixed $source, string $key): array => [
                    'key' => $key,
                    'status' => (string) ($this->publicArray($source)['status'] ?? 'unavailable'),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $report */
    private function publicRealtimeReport(array $report): array
    {
        $report['campaign'] = is_array($report['campaign'] ?? null) ? [
            'code' => $report['campaign']['code'] ?? null,
            'name' => $report['campaign']['name'] ?? null,
        ] : null;
        $report['agents'] = collect($report['agents'] ?? [])
            ->map(function (mixed $agent): array {
                $agent = $this->publicArray($agent);

                return [
                    'name' => $agent['name'] ?? null,
                    'state' => $agent['state'] ?? null,
                    'state_label' => $agent['state_label'] ?? null,
                    'calls_today' => $agent['calls_today'] ?? null,
                    'avg_handle' => $agent['avg_handle'] ?? null,
                    'avg_wait' => $agent['avg_wait'] ?? null,
                    'dispositions' => $agent['dispositions'] ?? null,
                    'vicidial_campaign' => $agent['vicidial_campaign'] ?? null,
                ];
            })
            ->values()
            ->all();
        $report['sources'] = collect($report['sources'] ?? [])
            ->map(fn (mixed $source, string $key): array => [
                'key' => $key,
                'status' => (string) ($this->publicArray($source)['status'] ?? 'unavailable'),
            ])
            ->values()
            ->all();

        unset($report['server'], $report['snapshot'], $report['active_calls']);

        return $report;
    }

    /** @param array<string, mixed> $dashboard */
    private function withoutDashboardAmounts(array $dashboard): array
    {
        unset($dashboard['kpis']['sales_amount'], $dashboard['kpis']['top_agent_sales_amount']);
        foreach (['agent_leaderboard', 'sales_by_form'] as $collection) {
            foreach (array_keys($dashboard['kpis'][$collection] ?? []) as $index) {
                unset($dashboard['kpis'][$collection][$index]['sales_amount']);
            }
        }

        unset($dashboard['summary']['amount_definition'], $dashboard['summary']['comparison']['amount']);
        foreach (['current', 'previous'] as $period) {
            unset($dashboard['summary']['summary'][$period]['amount']);
        }
        foreach (array_keys($dashboard['summary']['daily'] ?? []) as $index) {
            unset(
                $dashboard['summary']['daily'][$index]['current']['amount'],
                $dashboard['summary']['daily'][$index]['previous']['amount'],
            );
        }

        foreach (['daily', 'month_to_date'] as $period) {
            foreach (array_keys($dashboard['campaign_report'][$period] ?? []) as $index) {
                unset(
                    $dashboard['campaign_report'][$period][$index]['amounts'],
                    $dashboard['campaign_report'][$period][$index]['total_amount'],
                );
            }
            unset(
                $dashboard['campaign_report']['totals'][$period]['amounts'],
                $dashboard['campaign_report']['totals'][$period]['total_amount'],
            );
        }

        return $dashboard;
    }

    /** @return array<string, mixed> */
    private function publicArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        return is_object($value) ? get_object_vars($value) : [];
    }

    /** @param array<string, mixed> $filters */
    private function validateHistoricalRange(array $filters): void
    {
        $timezone = (string) ($filters['timezone'] ?? config('app.timezone'));
        $start = Carbon::parse((string) ($filters['query_date'] ?? now($timezone)->toDateString()), $timezone)->startOfDay();
        $end = Carbon::parse((string) ($filters['end_date'] ?? $start->toDateString()), $timezone)->startOfDay();

        if (abs($start->diffInDays($end)) >= self::MAX_HISTORICAL_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'end_date' => 'Historical reporting is limited to 31 days per request.',
            ]);
        }
    }
}

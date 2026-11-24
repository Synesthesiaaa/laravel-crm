@if(empty($forms))
    <div class="chart-container">
        <x-empty-state icon="chart-bar" title="No forms configured" description="This campaign has no forms to show submission activity for." />
    </div>
@else
    @php
        $singleForm = ($dashboardLayout['activity_mode'] ?? null) === \App\Services\DashboardLayoutService::ACTIVITY_SINGLE;
        $periodNames = ['daily' => 'Last 24 hours', 'weekly' => 'Weekly', 'monthly' => 'Monthly'];
        $calendarPeriods = ['weekly' => ['week', 'activity_week'], 'monthly' => ['month', 'activity_month']];
        $valuesByPeriod = array_map(static fn (array $trend): array => array_column($trend['forms'], 'values', 'code'), $activityTrends);
        $salesQuery = request()->hasAny(['sales_date', 'sales_start', 'sales_end']) ? [
            'sales_date' => $salesFilter['date'],
            'sales_start' => $salesFilter['start'],
            'sales_end' => $salesFilter['end'],
        ] : [];
        $periodUrl = static fn (string $parameter, string $date): string => route('dashboard', array_replace(
            $salesQuery + $activityQuery, [$parameter => $date],
        )).'#dashboard-activity-title';
    @endphp

    <div class="space-y-6" data-form-activity>
        <div class="flex flex-wrap gap-x-6 gap-y-3" data-activity-calendar>
            @foreach($calendarPeriods as $key => [$unit, $parameter])
                @php($period = $activityPeriods[$key])
                <nav class="flex items-center gap-2" aria-label="Browse by {{ $unit }}">
                    <a href="{{ $periodUrl($parameter, $period['previous']) }}" class="btn-secondary min-h-11 min-w-11 justify-center px-0" aria-label="Previous {{ $unit }}" title="Previous {{ $unit }}">
                        <x-icon name="chevron-left" class="size-4" />
                    </a>
                    <div class="min-w-0 px-1">
                        <p class="text-xs text-[var(--color-on-surface-muted)]">{{ ucfirst($unit) }}</p>
                        <p class="text-sm font-semibold tabular-nums" data-activity-range="{{ $key }}">{{ $period['label'] }}</p>
                    </div>
                    @if($period['next'] !== null)
                        <a href="{{ $periodUrl($parameter, $period['next']) }}" class="btn-secondary min-h-11 min-w-11 justify-center px-0" aria-label="Next {{ $unit }}" title="Next {{ $unit }}">
                            <x-icon name="chevron-right" class="size-4" />
                        </a>
                    @else
                        <button type="button" disabled class="btn-secondary min-h-11 min-w-11 justify-center px-0" aria-label="Next {{ $unit }}">
                            <x-icon name="chevron-right" class="size-4" />
                        </button>
                    @endif
                </nav>
            @endforeach
        </div>

        @if($singleForm)
            <div class="grid max-w-xl grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="dashboard-activity-form" class="text-sm font-semibold">Form</label>
                    <select id="dashboard-activity-form" class="form-select mt-1 min-h-11 w-full">
                        @foreach($forms as $code => $form)
                            <option value="{{ $code }}">{{ $form['name'] ?? $code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="dashboard-activity-period" class="text-sm font-semibold">Period</label>
                    <select id="dashboard-activity-period" class="form-select mt-1 min-h-11 w-full">
                        @foreach($periodNames as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        @foreach($forms as $code => $form)
            @php($formName = $form['name'] ?? $code)
            <section class="min-w-0 space-y-3" data-activity-form="{{ $code }}" aria-labelledby="activity-form-title-{{ $code }}" @if($singleForm && !$loop->first) hidden @endif>
                <h4 class="text-base font-semibold" id="activity-form-title-{{ $code }}">{{ $formName }}</h4>
                <div @class(['grid min-w-0 grid-cols-1 gap-3', 'xl:grid-cols-3' => !$singleForm])>
                    @foreach($periodNames as $key => $periodName)
                        @include('dashboard.form-activity-chart', [
                            'labels' => $activityTrends[$key]['labels'],
                            'values' => $valuesByPeriod[$key][$code] ?? array_fill(0, count($activityTrends[$key]['labels']), 0),
                            'rangeLabel' => $activityPeriods[$key]['label'] ?? null,
                            'panelHidden' => $singleForm && !$loop->first,
                        ])
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
@endif

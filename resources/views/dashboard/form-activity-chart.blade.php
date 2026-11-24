@php
    $chartId = 'chart-activity-'.$code.'-'.$key;
    $label = $formName.' · '.($rangeLabel ?? $periodName);
    $total = array_sum($values);
@endphp

<article class="chart-container min-w-0" data-activity-period="{{ $key }}" @if($panelHidden) hidden @endif>
    <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-1 xl:min-h-10">
        <div>
            <h5 class="text-sm font-semibold">{{ $periodName }}</h5>
            @if($rangeLabel !== null)
                <p class="mt-1 text-xs text-[var(--color-on-surface-muted)] tabular-nums">{{ $rangeLabel }}</p>
            @endif
        </div>
        <p class="text-sm text-[var(--color-on-surface-muted)]"><span class="font-semibold tabular-nums text-[var(--color-on-surface)]" data-activity-total>{{ number_format($total) }}</span> {{ Str::plural('submission', $total) }}</p>
    </div>

    <div class="mt-3 min-h-[180px] min-w-0">
        @if($total > 0)
            <div id="{{ $chartId }}" data-activity-chart="{{ $key }}" data-chart-values="{{ json_encode($values) }}" role="img" aria-label="Submissions for {{ $label }}"></div>
            <p data-activity-status class="py-4 text-sm text-[var(--color-on-surface-muted)]" hidden>Chart unavailable. Open Submission counts to view the data.</p>
        @else
            <p class="grid min-h-[180px] place-items-center text-center text-sm text-[var(--color-on-surface-muted)]">No submissions in this period.</p>
        @endif
    </div>

    <details id="{{ $chartId }}-counts" class="border-t border-[var(--color-border)]" data-activity-counts>
        <summary class="min-h-11 cursor-pointer py-3 text-sm font-semibold">Submission counts</summary>
        <div class="md-table-wrap">
            <table>
                <caption class="sr-only">Submission counts for {{ $label }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ $key === 'daily' ? 'Hour' : 'Day' }}</th>
                        <th scope="col" class="text-right">Submissions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($labels as $index => $bucket)
                        <tr>
                            <th scope="row" class="font-normal tabular-nums">{{ $bucket }}</th>
                            <td class="text-right tabular-nums">{{ number_format($values[$index] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2">No time buckets available.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
</article>

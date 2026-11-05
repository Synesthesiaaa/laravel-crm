@props(['report' => []])
<section {{ $attributes->class(['grid grid-cols-1 gap-4 sm:grid-cols-3']) }} aria-label="Verified call metrics">
    @foreach(['answer_rate' => 'Answer Rate', 'contact_rate' => 'Contact Rate', 'abandoned_rate' => 'Abandoned Rate'] as $key => $label)
        @php($metric = data_get($report, 'metrics.'.$key, []))
        <article class="md-card p-4" title="{{ $metric['definition'] ?? '' }}">
            <h3 class="text-sm font-semibold">{{ $label }}</h3>
            <p class="mt-2 text-2xl font-bold tabular-nums" data-call-metric="{{ $key }}">{{ isset($metric['rate']) ? number_format($metric['rate'], 2).'%' : 'N/A' }}</p>
            <p class="mt-2 text-xs text-[var(--color-on-surface-muted)]">{{ $metric['definition'] ?? '' }}</p>
            <p class="mt-2 text-xs text-[var(--color-on-surface-dim)]">
                @if(isset($metric['numerator'], $metric['denominator']))
                    {{ number_format($metric['numerator']) }} / {{ number_format($metric['denominator']) }}
                @else
                    {{ ucfirst(str_replace('_', ' ', $metric['reason'] ?? 'Verified evidence unavailable')) }}
                @endif
            </p>
        </article>
    @endforeach
    <p class="text-xs text-[var(--color-on-surface-dim)] sm:col-span-3">Verified attempt-start cohort · {{ data_get($report, 'scope.timezone', config('app.timezone')) }} · Repeated attempts and unique leads use separate denominators.</p>
</section>

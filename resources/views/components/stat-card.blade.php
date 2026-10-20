@props([
    'label',
    'value',
    'icon'     => 'chart-bar',
    'trend'    => null,
    'trendUp'  => null,
    'href'     => null,
    'color'    => 'primary',
    'loading'  => false,
    'secondary' => null,
    'trendDifference' => null,
    'trendLabel' => 'vs last period',
    'trendStatus' => null,
])
@php
$colorMap = [
    'primary' => ['bg' => 'var(--color-primary-muted)',  'text' => 'var(--color-primary)'],
    'success' => ['bg' => 'var(--color-success-muted)',  'text' => 'var(--color-success)'],
    'warning' => ['bg' => 'var(--color-warning-muted)',  'text' => 'var(--color-warning)'],
    'danger'  => ['bg' => 'var(--color-danger-muted)',   'text' => 'var(--color-danger)'],
    'info'    => ['bg' => 'var(--color-info-muted)',     'text' => 'var(--color-info)'],
];
$c = $colorMap[$color] ?? $colorMap['primary'];
$statCardClass = 'stat-card'.($href ? ' cursor-pointer hover:-translate-y-0.5 transition-transform' : '');
$trendState = $trendStatus ?? match (true) {
    $trendUp === true => 'increase',
    $trendUp === false => 'decrease',
    default => 'unchanged',
};
$trendClass = match ($trendState) {
    'increase', 'new' => 'up',
    'decrease' => 'down',
    default => 'flat',
};
$trendIcon = match ($trendState) {
    'increase', 'new' => 'arrow-trending-up',
    'decrease' => 'chevron-down',
    default => 'minus',
};
@endphp
<div {{ $attributes->merge(['class' => $statCardClass]) }}
     @if($href) onclick="window.location='{{ $href }}'" @endif>
    <div class="flex items-start justify-between">
        <span class="stat-card-label">{{ $label }}</span>
        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background: {{ $c['bg'] }}; color: {{ $c['text'] }}">
            <x-icon :name="$icon" class="w-4 h-4" />
        </div>
    </div>
    @if($loading)
        <div class="skeleton skeleton-title mt-1"></div>
    @else
        <div class="stat-card-value">{{ $value }}</div>
        @if($secondary !== null)
            <div class="stat-card-secondary">{{ $secondary }}</div>
        @endif
    @endif
    @if($trend !== null || $trendDifference !== null)
        <div class="stat-card-trend {{ $trendClass }}" aria-label="{{ ucfirst($trendState) }}{{ $trendDifference !== null ? ': '.$trendDifference : '' }}{{ $trend !== null ? ', '.number_format(abs((float) $trend), 2).' percent' : '' }}, {{ $trendLabel }}">
            <x-icon :name="$trendIcon" class="w-3.5 h-3.5" />
            <span class="sr-only">{{ ucfirst($trendState) }}</span>
            @if($trendDifference !== null)
                <span class="tabular-nums">{{ $trendDifference }}</span>
            @endif
            @if($trend !== null)
                <span class="tabular-nums">{{ number_format(abs((float) $trend), 2) }}%</span>
            @endif
            <span class="font-normal text-[var(--color-on-surface-dim)]">{{ $trendLabel }}</span>
        </div>
    @endif
</div>

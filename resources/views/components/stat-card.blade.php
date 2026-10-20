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
$statCardClass = 'stat-card'.($href ? ' stat-card--link' : '');
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
<{{ $href ? 'a' : 'div' }} {{ $attributes->merge(['class' => $statCardClass]) }}
     @if($href) href="{{ $href }}" @endif @if($loading) aria-busy="true" @endif>
    <span class="stat-card-label">{{ $label }}</span>
    @if($loading)
        <div class="skeleton skeleton-title mt-1" aria-hidden="true"></div>
        <span class="sr-only">Loading {{ $label }}</span>
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
</{{ $href ? 'a' : 'div' }}>

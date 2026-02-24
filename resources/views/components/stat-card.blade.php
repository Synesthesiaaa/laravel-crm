@props([
    'label',
    'value',
    'icon'     => 'chart-bar',
    'trend'    => null,
    'trendUp'  => null,
    'href'     => null,
    'color'    => 'primary',
    'loading'  => false,
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
@endphp
<div class="stat-card {{ $href ? 'cursor-pointer hover:-translate-y-0.5 transition-transform' : '' }}"
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
    @endif
    @if($trend !== null)
        <div class="stat-card-trend {{ $trendUp ? 'up' : 'down' }}">
            <x-icon :name="$trendUp ? 'arrow-trending-up' : 'chevron-down'" class="w-3.5 h-3.5" />
            <span>{{ abs($trend) }}%</span>
            <span class="font-normal text-[var(--color-on-surface-dim)]">vs last period</span>
        </div>
    @endif
</div>

@props(['log'])

@php
    $eventTime = $log->event_time?->copy()->timezone(config('app.timezone'));
@endphp

@if($eventTime)
    <time
        datetime="{{ $log->event_time->toIso8601String() }}"
        title="{{ $eventTime->format('Y-m-d H:i:s T') }}"
        {{ $attributes->class('inline-flex flex-col whitespace-nowrap') }}
    >
        <span class="text-sm font-medium text-[var(--color-on-surface)]">{{ $eventTime->format('M j, Y') }}</span>
        <span class="font-mono text-xs tabular-nums text-[var(--color-on-surface-muted)]">{{ $eventTime->format('g:i:s A T') }}</span>
    </time>
@else
    <span class="text-[var(--color-on-surface-dim)]">—</span>
@endif

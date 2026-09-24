@props(['log'])

@php
    $type = $log->event_type === 'login'
        ? 'active'
        : ($log->event_type === 'logout' ? 'inactive' : 'info');
@endphp

<x-badge :type="$type">
    {{ $log->eventDisplayLabel() }}
</x-badge>

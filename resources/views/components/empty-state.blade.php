@props([
    'icon' => 'information-circle',
    'title' => 'No data available',
    'description' => null,
    'actionText' => null,
    'actionHref' => null,
    'tone' => 'neutral',
])
@php
    $tone = in_array($tone, ['neutral', 'info', 'warning', 'danger'], true) ? $tone : 'neutral';
@endphp
<div {{ $attributes->class(['crm-empty-state', 'crm-empty-state--'.$tone]) }} role="status" aria-live="polite">
    <span class="crm-empty-state-icon" aria-hidden="true">
        <x-icon :name="$icon" class="w-5 h-5" />
    </span>
    <div class="min-w-0 max-w-xl">
        <p class="crm-empty-state-title">{{ $title }}</p>
        @if($description)
            <p class="crm-empty-state-description">{{ $description }}</p>
        @endif
        @if(isset($slot) && $slot->isNotEmpty())
            <div class="crm-empty-state-details">{{ $slot }}</div>
        @endif
        @if($actionText && $actionHref)
            <a href="{{ $actionHref }}" class="btn-secondary text-xs mt-3 inline-flex">{{ $actionText }}</a>
        @endif
    </div>
</div>

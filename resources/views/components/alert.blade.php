@props(['type' => 'info', 'dismissible' => false, 'title' => null])
@php
$icons = ['success' => 'check-circle', 'error' => 'x-circle', 'warning' => 'exclamation-triangle', 'info' => 'information-circle'];
$icon  = $icons[$type] ?? 'information-circle';
@endphp
<div class="alert alert-{{ $type }}" role="alert" @if($dismissible) x-data="{ show: true }" x-show="show" @endif>
    <x-icon :name="$icon" class="w-4 h-4 shrink-0 mt-0.5" />
    <div class="flex-1 min-w-0">
        @if($title) <p class="font-semibold mb-0.5">{{ $title }}</p> @endif
        <div class="text-sm">{{ $slot }}</div>
    </div>
    @if($dismissible)
        <button @click="show = false" class="shrink-0 opacity-60 hover:opacity-100" aria-label="Dismiss">
            <x-icon name="x-mark" class="w-4 h-4" />
        </button>
    @endif
</div>

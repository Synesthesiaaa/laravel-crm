@props(['type' => 'spinner', 'size' => 'md'])
@php $sizes = ['sm' => 'w-4 h-4', 'md' => 'w-6 h-6', 'lg' => 'w-8 h-8']; $sz = $sizes[$size] ?? $sizes['md']; @endphp
@if($type === 'spinner')
    <div class="flex items-center justify-center {{ $sz }}" role="status" aria-label="Loading">
        <x-icon name="arrow-path" class="{{ $sz }} animate-spin text-[var(--color-primary)]" />
    </div>
@elseif($type === 'overlay')
    <div class="flex flex-col items-center justify-center gap-3 py-12">
        <x-icon name="arrow-path" class="w-8 h-8 animate-spin text-[var(--color-primary)]" />
        @if($slot->isNotEmpty())
            <p class="text-sm text-[var(--color-on-surface-muted)]">{{ $slot }}</p>
        @endif
    </div>
@elseif($type === 'skeleton-card')
    <div class="stat-card animate-pulse">
        <div class="skeleton skeleton-text w-24 mb-3"></div>
        <div class="skeleton h-8 w-16 mb-2"></div>
        <div class="skeleton skeleton-text w-20"></div>
    </div>
@endif

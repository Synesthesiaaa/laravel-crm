@props(['title' => null, 'description' => null, 'cols' => 2])
@php $colClass = match((int)$cols) { 1 => 'grid-cols-1', 3 => 'grid-cols-1 md:grid-cols-3', default => 'grid-cols-1 md:grid-cols-2' }; @endphp
<div class="md-card p-6 mb-4">
    @if($title)
    <div class="mb-5">
        <h3 class="text-sm font-bold text-[var(--color-on-surface)] uppercase tracking-wider">{{ $title }}</h3>
        @if($description)
            <p class="text-xs text-[var(--color-on-surface-muted)] mt-1">{{ $description }}</p>
        @endif
    </div>
    @endif
    <div class="grid {{ $colClass }} gap-4">
        {{ $slot }}
    </div>
</div>

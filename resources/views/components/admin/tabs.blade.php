@props([
    'tabs' => [],
    'active' => null,
    'label' => 'Sections',
])

<nav aria-label="{{ $label }}" class="border-b border-[var(--color-border)] px-3 pt-3 sm:px-4">
    <div class="flex max-w-full gap-1 overflow-x-auto pb-3">
        @foreach($tabs as $tab)
            @php($isActive = ($tab['key'] ?? null) === $active)
            <a href="{{ $tab['href'] ?? '#' }}"
               class="inline-flex min-h-10 shrink-0 items-center rounded-lg px-3 py-2 text-sm font-semibold transition-colors {{ $isActive ? 'bg-[var(--color-primary)] text-white' : 'text-[var(--color-on-surface-muted)] hover:bg-[var(--color-surface-2)] hover:text-[var(--color-on-surface)]' }}"
               @if($isActive) aria-current="page" @endif>
                {{ $tab['label'] ?? $tab['key'] ?? '' }}
            </a>
        @endforeach
    </div>
</nav>

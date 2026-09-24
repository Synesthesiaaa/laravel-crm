@props([
    'title' => null,
    'description' => null,
])

<section {{ $attributes->class('md-card md-card--static') }}>
    @if($title || $description || isset($actions))
        <div class="flex flex-col gap-3 border-b border-[var(--color-border)] px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
            <div class="min-w-0">
                @if($title)
                    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">{{ $title }}</h3>
                @endif
                @if($description)
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-[var(--color-on-surface-muted)]">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="p-5 sm:p-6">
        {{ $slot }}
    </div>
</section>

@props([
    'title',
    'items' => [],
    'tone' => 'default',
])

@php
    $headingId = 'admin-tool-grid-'.\Illuminate\Support\Str::slug($title);
@endphp

<section aria-labelledby="{{ $headingId }}">
    <h3 id="{{ $headingId }}" class="mb-4 text-sm font-semibold text-[var(--color-on-surface-muted)]">
        {{ $title }}
    </h3>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 animate-stagger">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}"
               class="md-card md-card--interactive group flex min-h-24 items-center gap-3 p-4 no-underline">
                <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0 text-[var(--color-on-surface-muted)]" />

                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-[var(--color-on-surface)]">{{ $item['label'] }}</span>
                    <span class="mt-0.5 block text-xs leading-5 text-[var(--color-on-surface-dim)]">{{ $item['desc'] }}</span>
                </span>

                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-[var(--color-on-surface-dim)] transition-transform group-hover:translate-x-0.5 group-hover:text-[var(--color-on-surface-muted)]" />
            </a>
        @endforeach
    </div>
</section>

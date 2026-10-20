@props([
    'title',
    'items' => [],
    'tone' => 'default',
])

@php
    $headingId = 'admin-tool-grid-'.\Illuminate\Support\Str::slug($title);
    $dangerTone = $tone === 'danger';
@endphp

<section aria-labelledby="{{ $headingId }}">
    <h3 id="{{ $headingId }}" class="mb-4 text-xs font-bold uppercase tracking-widest text-[var(--color-on-surface-dim)]">
        {{ $title }}
    </h3>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 animate-stagger">
        @foreach($items as $item)
            <a href="{{ route($item['route']) }}"
               class="md-card md-card--interactive group flex min-h-24 items-center gap-3 p-4 no-underline">
                <span @class([
                    'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[var(--color-border)] transition-colors',
                    'bg-[var(--color-danger-muted)] group-hover:border-[var(--color-danger)]' => $dangerTone,
                    'bg-[var(--color-surface-2)] group-hover:border-[var(--color-primary)] group-hover:bg-[var(--color-primary-muted)]' => ! $dangerTone,
                ])>
                    <x-icon :name="$item['icon']" @class([
                        'h-5 w-5',
                        'text-[var(--color-danger-fg)]' => $dangerTone,
                        'text-[var(--color-on-surface-muted)] group-hover:text-[var(--color-primary)]' => ! $dangerTone,
                    ]) />
                </span>

                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-semibold text-[var(--color-on-surface)]">{{ $item['label'] }}</span>
                    <span class="mt-0.5 block text-xs leading-5 text-[var(--color-on-surface-dim)]">{{ $item['desc'] }}</span>
                </span>

                <x-icon name="chevron-right" class="h-4 w-4 shrink-0 text-[var(--color-on-surface-dim)] transition-transform group-hover:translate-x-0.5 group-hover:text-[var(--color-on-surface-muted)]" />
            </a>
        @endforeach
    </div>
</section>

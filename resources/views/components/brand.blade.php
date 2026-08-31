@props([
    'branding' => [],
    'variant' => 'default',
    'showName' => true,
])

@php
    $brandName = trim((string) data_get($branding, 'name', 'CRM')) ?: 'CRM';
    $logoUrl = data_get($branding, 'logo_url');
    $logoAlt = data_get($branding, 'logo_alt', $brandName.' logo');
    $rootClasses = match ($variant) {
        'sidebar' => 'flex items-center gap-3 min-w-0',
        'login' => 'flex flex-col items-center gap-3 mb-6 text-center',
        'preview' => 'flex items-center gap-3 min-w-0',
        default => 'flex items-center gap-3 min-w-0',
    };
    $markClasses = match ($variant) {
        'sidebar' => 'sidebar-logo',
        'login' => 'flex h-20 w-20 items-center justify-center rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3',
        'preview' => 'flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] p-2',
        default => 'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--color-primary-muted)] p-1.5',
    };
    $nameClasses = match ($variant) {
        'sidebar' => 'sidebar-brand-text max-w-[13rem] truncate font-bold uppercase tracking-wider text-[var(--color-on-surface)]',
        'login' => 'max-w-full break-words text-lg font-semibold text-[var(--color-on-surface)]',
        'preview' => 'min-w-0 break-words text-sm font-semibold text-[var(--color-on-surface)]',
        default => 'min-w-0 break-words font-semibold text-[var(--color-on-surface)]',
    };
    $fallbackIconClasses = match ($variant) {
        'sidebar' => 'h-5 w-5 text-[var(--color-primary)]',
        'login' => 'h-10 w-10 text-[var(--color-primary)]',
        'preview' => 'h-8 w-8 text-[var(--color-primary)]',
        default => 'h-6 w-6 text-[var(--color-primary)]',
    };
@endphp

<div {{ $attributes->merge(['class' => $rootClasses]) }} title="{{ $brandName }}">
    <span class="{{ $markClasses }}">
        @if($logoUrl)
            <img src="{{ $logoUrl }}"
                 alt="{{ $logoAlt }}"
                 class="h-full w-full object-contain"
                 width="80"
                 height="80"
                 decoding="async">
        @else
            <x-icon name="signal" class="{{ $fallbackIconClasses }}" />
        @endif
    </span>

    @if($showName)
        <span class="{{ $nameClasses }}">{{ $brandName }}</span>
    @endif
</div>

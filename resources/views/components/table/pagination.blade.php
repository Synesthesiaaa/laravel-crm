@props(['paginator'])
@if($paginator->total() > 0)
<div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-[var(--color-border)]">
    <p class="text-xs text-[var(--color-on-surface-dim)]">
        Showing <span class="font-semibold text-[var(--color-on-surface)]">{{ $paginator->firstItem() }}</span>
        to <span class="font-semibold text-[var(--color-on-surface)]">{{ $paginator->lastItem() }}</span>
        of <span class="font-semibold text-[var(--color-on-surface)]">{{ $paginator->total() }}</span> results
    </p>
    @if($paginator->hasPages())
        {{ $paginator->withQueryString()->links() }}
    @endif
</div>
@endif

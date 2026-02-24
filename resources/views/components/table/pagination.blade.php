@props(['paginator'])
@if($paginator->hasPages())
<div class="flex items-center justify-between px-4 py-3 border-t border-[var(--color-border)]">
    <p class="text-xs text-[var(--color-on-surface-dim)]">
        Showing <span class="font-semibold text-[var(--color-on-surface)]">{{ $paginator->firstItem() }}</span>
        to <span class="font-semibold text-[var(--color-on-surface)]">{{ $paginator->lastItem() }}</span>
        of <span class="font-semibold text-[var(--color-on-surface)]">{{ $paginator->total() }}</span> results
    </p>
    {{ $paginator->withQueryString()->links() }}
</div>
@endif

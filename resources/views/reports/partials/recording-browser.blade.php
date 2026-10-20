<div class="md-card p-4 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Recording Browser</h3>
    @include('reports.partials.recording-filters')
    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)]"
         x-text="payloads.recording?.data?.raw_response || payloads.recording?.message || 'No recording data yet.'"></pre>
</div>

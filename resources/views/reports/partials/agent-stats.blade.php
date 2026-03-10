<div class="md-card p-4">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)] mb-3">Agent Performance</h3>
    <p class="text-xs text-[var(--color-on-surface-dim)] mb-2">Raw VICIdial output</p>
    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)]"
         x-text="payloads.agents?.data?.raw_response || payloads.agents?.message || 'No data yet.'"></pre>
</div>

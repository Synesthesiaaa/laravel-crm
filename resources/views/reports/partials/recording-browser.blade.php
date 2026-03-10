<div class="md-card p-4 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Recording Browser</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input class="form-input" type="text" placeholder="Agent user" x-model="recordingFilters.agent_user" />
        <input class="form-input" type="number" placeholder="Lead ID" x-model="recordingFilters.lead_id" />
        <input class="form-input" type="date" x-model="recordingFilters.date" />
        <button class="btn-secondary" @click="lookupRecordings(recordingFilters)">Search</button>
    </div>
    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)]"
         x-text="payloads.recording?.data?.raw_response || payloads.recording?.message || 'No recording data yet.'"></pre>
</div>

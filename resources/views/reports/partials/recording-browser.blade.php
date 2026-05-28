<div class="md-card p-4 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Recording Browser</h3>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input class="form-input" type="text" placeholder="Agent user" x-model="recordingFilters.agent_user" :disabled="recordingLoading" />
        <input class="form-input" type="number" placeholder="Lead ID" x-model="recordingFilters.lead_id" :disabled="recordingLoading" />
        <input class="form-input" type="date" x-model="recordingFilters.date" :disabled="recordingLoading" />
        <button class="btn-secondary" @click="lookupRecordings(recordingFilters)" :disabled="recordingLoading">
            <x-icon name="arrow-path" class="w-4 h-4" x-bind:class="recordingLoading ? 'animate-spin' : ''" />
            <span x-text="recordingLoading ? 'Searching...' : 'Search'">Search</span>
        </button>
    </div>
    <template x-if="recordingLoading && !payloads.recording">
        <div class="flex items-center gap-2 text-xs text-[var(--color-on-surface-dim)]">
            <x-icon name="arrow-path" class="w-4 h-4 animate-spin" />
            Looking up recordings...
        </div>
    </template>
    <pre class="text-xs whitespace-pre-wrap break-words bg-[var(--color-surface-2)] p-3 rounded border border-[var(--color-border)]"
         x-text="payloads.recording?.data?.raw_response || payloads.recording?.message || 'No recording data yet.'"></pre>
</div>

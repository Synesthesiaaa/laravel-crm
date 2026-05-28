<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Callback Scheduler</h3>
    <div class="grid grid-cols-1 gap-2">
        <input class="form-input" type="datetime-local" x-model="callbackForm.datetime" />
        <select class="form-select" x-model="callbackForm.type">
            <option value="ANYONE">ANYONE</option>
            <option value="USERONLY">USERONLY</option>
        </select>
        <input class="form-input" x-model="callbackForm.user" placeholder="Callback user (for USERONLY)" />
        <textarea class="form-textarea" rows="2" x-model="callbackForm.comments" placeholder="Comments"></textarea>
    </div>
    <div class="flex gap-2">
        <button class="btn-secondary text-xs" @click="scheduleCallback()" :disabled="busy.callback || !leadId || !callbackForm.datetime">
            <span x-text="busy.callback === 'schedule' ? 'Scheduling...' : 'Schedule'">Schedule</span>
        </button>
        <button class="btn-ghost text-xs" @click="removeCallback()" :disabled="busy.callback || !leadId">
            <span x-text="busy.callback === 'remove' ? 'Removing...' : 'Remove'">Remove</span>
        </button>
        <button class="btn-ghost text-xs" @click="callbackInfo()" :disabled="busy.callback || !leadId">
            <span x-text="busy.callback === 'info' ? 'Loading...' : 'Info'">Info</span>
        </button>
    </div>
</div>

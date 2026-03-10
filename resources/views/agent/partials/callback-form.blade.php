<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Callback Scheduler</h3>
    <div class="grid grid-cols-1 gap-2">
        <input class="form-input" type="datetime-local" x-model="callback.datetime" />
        <select class="form-select" x-model="callback.type">
            <option value="ANYONE">ANYONE</option>
            <option value="USERONLY">USERONLY</option>
        </select>
        <input class="form-input" x-model="callback.user" placeholder="Callback user (for USERONLY)" />
        <textarea class="form-textarea" rows="2" x-model="callback.comments" placeholder="Comments"></textarea>
    </div>
    <div class="flex gap-2">
        <button class="btn-secondary text-xs" @click="scheduleCallback()">Schedule</button>
        <button class="btn-ghost text-xs" @click="removeCallback()">Remove</button>
        <button class="btn-ghost text-xs" @click="callbackInfo()">Info</button>
    </div>
</div>

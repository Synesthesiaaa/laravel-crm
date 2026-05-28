<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">In-Group Management</h3>
    <div class="form-field">
        <label class="form-label">In-groups (space or comma separated)</label>
        <input class="form-input" x-model="$store.vicidial.ingroupsRaw" placeholder="SALESLINE SUPPORT AGENTDIRECT" />
    </div>
    <div class="flex items-center gap-2">
        <label class="inline-flex items-center gap-2 text-xs text-[var(--color-on-surface-muted)]">
            <input type="checkbox" x-model="$store.vicidial.blended" />
            Blended mode
        </label>
    </div>
    <div class="flex gap-2">
        <button class="btn-secondary text-xs" @click="updateIngroups('CHANGE')" :disabled="busy.ingroups">
            <span x-text="busy.ingroups === 'CHANGE' ? 'Replacing...' : 'Replace'">Replace</span>
        </button>
        <button class="btn-secondary text-xs" @click="updateIngroups('ADD')" :disabled="busy.ingroups">
            <span x-text="busy.ingroups === 'ADD' ? 'Adding...' : 'Add'">Add</span>
        </button>
        <button class="btn-secondary text-xs" @click="updateIngroups('REMOVE')" :disabled="busy.ingroups">
            <span x-text="busy.ingroups === 'REMOVE' ? 'Removing...' : 'Remove'">Remove</span>
        </button>
    </div>
</div>

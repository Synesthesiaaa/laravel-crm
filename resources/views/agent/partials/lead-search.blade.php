<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Lead Search & Actions</h3>
    <div class="grid grid-cols-1 gap-2">
        <input class="form-input" x-model="leadTools.phone_search" placeholder="Phone number for lead search" />
        <div class="flex gap-2">
            <button class="btn-secondary text-xs" @click="searchLead()" :disabled="busy.lead || !leadTools.phone_search">
                <span x-text="busy.lead === 'search' ? 'Searching...' : 'Search'">Search</span>
            </button>
            <button class="btn-secondary text-xs" @click="loadLeadInfo()" :disabled="busy.lead">
                <span x-text="busy.lead === 'info' ? 'Loading...' : 'Load Info'">Load Info</span>
            </button>
            <button class="btn-secondary text-xs" @click="switchLead()" :disabled="busy.lead || !leadId">
                <span x-text="busy.lead === 'switch' ? 'Switching...' : 'Switch Lead'">Switch Lead</span>
            </button>
        </div>
        <textarea class="form-textarea" rows="3" x-model="leadTools.raw" placeholder="Lead API raw response"></textarea>
    </div>
</div>

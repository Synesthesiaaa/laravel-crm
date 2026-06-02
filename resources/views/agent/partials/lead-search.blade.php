<div class="md-card p-5 space-y-3">
    <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">Lead Search & Actions</h3>
    <div class="grid grid-cols-1 gap-2">
        <input class="form-input" x-model="leadTools.phone_search" placeholder="Phone number for lead search" />
        <div class="flex gap-2">
            <button class="btn-secondary text-xs" @click="searchLead()">Search</button>
            <button class="btn-secondary text-xs" @click="loadLeadInfo()">Load Info</button>
            <button class="btn-secondary text-xs" @click="switchLead()">Switch Lead</button>
        </div>
        <textarea class="form-textarea" rows="3" x-model="leadTools.raw" placeholder="Lead API raw response"></textarea>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="form-field"><label class="form-label" for="recording-agent">Agent user</label><input id="recording-agent" class="form-input" type="text" placeholder="Agent user" x-model="recordingFilters.agent_user" /></div>
    <div class="form-field"><label class="form-label" for="recording-lead">Lead ID</label><input id="recording-lead" class="form-input" type="number" placeholder="Lead ID" x-model="recordingFilters.lead_id" /></div>
    <div class="form-field"><label class="form-label" for="recording-date">Recording date</label><input id="recording-date" class="form-input" type="date" x-model="recordingFilters.date" /></div>
    <button type="button" class="btn-secondary self-end" @click="lookupRecordings(recordingFilters)">Search</button>
</div>

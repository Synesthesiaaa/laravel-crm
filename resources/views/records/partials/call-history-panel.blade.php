@php
    $personal = $personal ?? false;
    $initialFilters = request()->only([
        'start_date', 'end_date', 'agent', 'phone', 'lead_id', 'status',
        'disposition', 'vicidial_campaign', 'direction', 'sort', 'dir', 'page', 'per_page',
    ]);
@endphp

<section
    x-data="callHistoryPage({ campaign: @js($campaign), personal: @js($personal), initialFilters: @js($initialFilters) })"
    x-init="init()"
    x-cloak
    :aria-busy="loading || refreshing ? 'true' : 'false'"
    aria-labelledby="call-history-heading"
>
    <div class="md-card mb-4 md-card--static">
        <div class="p-4">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                <div>
                    <h2 id="call-history-heading" class="text-sm font-semibold text-[var(--color-on-surface)]">Historical VICIdial calls</h2>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1">
                        Locally synchronized history · <span x-text="mappedCampaignCount"></span> mapped campaign<span x-show="mappedCampaignCount !== 1">s</span>
                        · <span x-text="syncDetailLabel"></span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="badge"
                        :class="healthStatus === 'healthy' ? 'badge-active' : (healthStatus === 'stale' ? 'badge-pending' : 'badge-error')"
                        x-text="healthLabel"
                    ></span>
                    <button type="button" class="btn-ghost" @click="refresh()" :disabled="refreshing" :aria-busy="refreshing ? 'true' : 'false'">
                        <span x-show="!refreshing">Refresh</span>
                        <span x-show="refreshing">Refreshing…</span>
                    </button>
                </div>
            </div>

            <form class="filter-row" @submit.prevent="applyFilters()">
                <label class="form-field">
                    <span class="form-label">Start Date</span>
                    <input class="form-input" type="date" x-model="filters.start_date">
                </label>
                <label class="form-field">
                    <span class="form-label">End Date</span>
                    <input class="form-input" type="date" x-model="filters.end_date">
                </label>
                <label class="form-field">
                    <span class="form-label">Agent</span>
                    <select class="form-input" x-model="filters.agent" @change="applyFilters()">
                        <option value="">All agents</option>
                        <template x-for="agent in filterOptions.agents || []" :key="agent.value">
                            <option :value="agent.value" x-text="agent.label"></option>
                        </template>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Phone</span>
                    <input class="form-input" type="tel" placeholder="Phone number" x-model.debounce.450ms="filters.phone" @input="queueTextFilter()">
                </label>
                <label class="form-field">
                    <span class="form-label">Status</span>
                    <select class="form-input" x-model="filters.status" @change="applyFilters()">
                        <option value="">All statuses</option>
                        <template x-for="status in filterOptions.statuses || []" :key="status">
                            <option :value="status" x-text="status"></option>
                        </template>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Disposition</span>
                    <select class="form-input" x-model="filters.disposition" @change="applyFilters()">
                        <option value="">All dispositions</option>
                        <template x-for="(label, code) in filterOptions.dispositions || {}" :key="code">
                            <option :value="code" x-text="label"></option>
                        </template>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">VICIdial Campaign</span>
                    <select class="form-input" x-model="filters.vicidial_campaign" @change="applyFilters()">
                        <option value="">All mapped campaigns</option>
                        <template x-for="campaignCode in filterOptions.campaigns || []" :key="campaignCode">
                            <option :value="campaignCode" x-text="campaignCode"></option>
                        </template>
                    </select>
                </label>
                <label class="form-field">
                    <span class="form-label">Direction</span>
                    <select class="form-input" x-model="filters.direction" @change="applyFilters()">
                        <option value="">All directions</option>
                        <option value="OUTBOUND">Outbound</option>
                        <option value="INBOUND">Inbound</option>
                    </select>
                </label>
                <div class="form-actions-bottom">
                    <button type="submit" class="btn-primary">Filter</button>
                    <button type="button" class="btn-ghost" @click="clearFilters()">Clear</button>
                </div>
            </form>
        </div>
    </div>

    <div x-show="state === 'stale' && !loading" class="alert alert-warning mb-4" role="status">
        <span x-text="healthMessage || 'Showing locally stored data while synchronization catches up.'"></span>
    </div>
    <div x-show="state === 'unavailable' && !loading" class="alert alert-error mb-4" role="alert">
        <span x-text="message || 'Call History could not be loaded.'"></span>
        <button type="button" class="link-primary ml-1" @click="load()">Retry</button>
    </div>

    <div x-show="loading" class="md-card md-card--static p-4" aria-live="polite">
        <div class="space-y-3" aria-hidden="true">
            <template x-for="row in 5" :key="row">
                <div class="h-10 rounded bg-[var(--color-surface-container-high)] animate-pulse"></div>
            </template>
        </div>
        <p class="sr-only">Loading Call History</p>
    </div>

    <div x-show="!loading" class="md-card md-card--static overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left" aria-describedby="call-history-status">
                <caption class="sr-only">Historical VICIdial call history</caption>
                <thead>
                    <tr>
                        <template x-for="column in columns" :key="column.key">
                            <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)] whitespace-nowrap">
                                <button type="button" class="hover:text-[var(--color-primary)]" x-show="column.sortable" @click="setSort(column.key)" x-text="column.label"></button>
                                <span x-show="!column.sortable" x-text="column.label"></span>
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody x-show="state === 'data'">
                    <template x-for="row in records" :key="row.id">
                        <tr class="border-t border-[var(--color-border)]">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-[var(--color-on-surface-muted)]" x-text="formatDate(row.called_at)"></td>
                            <td class="px-4 py-3">
                                <div x-text="row.agent.name"></div>
                                <div class="text-xs text-[var(--color-on-surface-dim)]" x-show="row.agent.vicidial_user" x-text="row.agent.vicidial_user + (row.agent.crm_user_available ? '' : ' · CRM user unavailable')"></div>
                            </td>
                            <td class="px-4 py-3 font-mono text-sm" x-text="row.phone_number || '—'"></td>
                            <td class="px-4 py-3"><span class="badge" :class="statusBadge(row.status)" x-text="row.status || 'Unknown'"></span></td>
                            <td class="px-4 py-3"><span x-text="row.disposition.label"></span><span class="text-xs text-[var(--color-on-surface-dim)]" x-show="row.disposition.code" x-text="' (' + row.disposition.code + ')' "></span></td>
                            <td class="px-4 py-3 font-mono text-sm" x-text="formatDuration(row.duration_seconds)"></td>
                            <td class="px-4 py-3 font-mono text-sm" x-text="row.vicidial_campaign || '—'"></td>
                            <td class="px-4 py-3">
                                <details class="text-sm">
                                    <summary class="cursor-pointer text-[var(--color-primary)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2">View</summary>
                                    <dl class="mt-2 min-w-48 space-y-1 text-xs text-[var(--color-on-surface-dim)]">
                                        <div class="flex justify-between gap-3"><dt>Direction</dt><dd x-text="row.call_direction"></dd></div>
                                        <div class="flex justify-between gap-3"><dt>Lead ID</dt><dd x-text="row.lead_id || '—'"></dd></div>
                                        <div class="flex justify-between gap-3"><dt>List ID</dt><dd x-text="row.vicidial_list || '—'"></dd></div>
                                        <div class="flex justify-between gap-3"><dt>Wait time</dt><dd x-text="formatDuration(row.wait_seconds)"></dd></div>
                                        <div class="flex justify-between gap-3"><dt>End reason</dt><dd x-text="row.raw_end_reason || '—'"></dd></div>
                                        <div class="flex justify-between gap-3"><dt>Call ID</dt><dd class="font-mono" x-text="row.unique_call_id"></dd></div>
                                    </dl>
                                </details>
                            </td>
                        </tr>
                    </template>
                </tbody>
                <tbody x-show="state === 'confirmed_empty' || state === 'syncing'">
                    <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-[var(--color-on-surface-dim)]">
                        <span x-show="state === 'confirmed_empty'">No calls were found. Try a wider date range or clear one of the filters.</span>
                        <span x-show="state === 'syncing'">Local Call History is waiting for its first synchronization.</span>
                    </td></tr>
                </tbody>
            </table>
        </div>
        <div id="call-history-status" class="flex flex-wrap items-center justify-between gap-3 border-t border-[var(--color-border)] px-4 py-3 text-sm text-[var(--color-on-surface-dim)]" aria-live="polite">
            <span x-text="paginationLabel"></span>
            <div class="flex items-center gap-2">
                <button type="button" class="btn-ghost" @click="goToPage(pagination.current_page - 1)" :disabled="!pagination.current_page || pagination.current_page <= 1">Previous</button>
                <span x-text="pagination.last_page ? pagination.current_page + ' / ' + pagination.last_page : '—'"></span>
                <button type="button" class="btn-ghost" @click="goToPage(pagination.current_page + 1)" :disabled="!pagination.has_more_pages">Next</button>
            </div>
        </div>
    </div>
</section>

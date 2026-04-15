@extends('layouts.app')

@section('title', 'Telephony Reports')
@section('header-icon')<x-icon name="chart-bar" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Telephony Reports')

@section('content')
<div x-data="telephonyReports()" x-init="init()" class="space-y-6">
    <x-page-header title="Telephony Reports"
        description="VICIdial reporting surfaced directly in CRM."
        :breadcrumbs="['Dashboard' => route('dashboard'), 'Reports' => null]" />

    <div class="md-card p-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="form-field">
                <label class="form-label">Campaigns</label>
                <input class="form-input" x-model="filters.campaigns" placeholder="---ALL--- or TESTCAMP" />
            </div>
            <div class="form-field">
                <label class="form-label">Date Start</label>
                <input class="form-input" type="date" x-model="filters.query_date" />
            </div>
            <div class="form-field">
                <label class="form-label">Date End</label>
                <input class="form-input" type="date" x-model="filters.end_date" />
            </div>
            <div class="form-field flex items-end">
                <button class="btn-primary w-full" @click="refreshAll()" :disabled="loading">
                    <x-icon name="arrow-path" class="w-4 h-4" />
                    <span x-text="loading ? 'Loading...' : 'Refresh Reports'">Refresh Reports</span>
                </button>
            </div>
        </div>
    </div>

    <div class="flex gap-2 border-b border-[var(--color-border)]" role="tablist">
        <button type="button" role="tab" :aria-selected="tab === 'status'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'status' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="setTab('status')">
            Call Status Stats
        </button>
        <button type="button" role="tab" :aria-selected="tab === 'agents'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'agents' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="setTab('agents')">
            Agent Performance
        </button>
        <button type="button" role="tab" :aria-selected="tab === 'dispo'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'dispo' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="setTab('dispo')">
            Disposition Report
        </button>
        <button type="button" role="tab" :aria-selected="tab === 'recording'"
                class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'recording' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="setTab('recording')">
            Recording Browser
        </button>
    </div>

    <div x-show="tab === 'status'">@include('reports.partials.call-status')</div>
    <div x-show="tab === 'agents'">@include('reports.partials.agent-stats')</div>
    <div x-show="tab === 'dispo'">@include('reports.partials.dispo-report')</div>
    <div x-show="tab === 'recording'">@include('reports.partials.recording-browser')</div>
</div>
@endsection

@push('scripts')
<script>
window.telephonyReports = function () {
    const ALLOWED_TABS = ['status', 'agents', 'dispo', 'recording'];

    return {
        tab: 'status',
        loading: false,
        filters: {
            campaigns: '---ALL---',
            query_date: new Date().toISOString().slice(0, 10),
            end_date: new Date().toISOString().slice(0, 10),
        },
        recordingFilters: {
            agent_user: '',
            lead_id: '',
            date: new Date().toISOString().slice(0, 10),
        },
        payloads: {
            status: null,
            agents: null,
            dispo: null,
            recording: null,
        },

        init() {
            this.syncTabFromUrl();
            this._onPopState = () => this.syncTabFromUrl();
            window.addEventListener('popstate', this._onPopState);
            this.refreshAll();
        },

        syncTabFromUrl() {
            const params = new URLSearchParams(window.location.search);
            const t = params.get('tab');
            this.tab = t && ALLOWED_TABS.includes(t) ? t : 'status';
        },

        setTab(name) {
            if (!ALLOWED_TABS.includes(name)) {
                return;
            }
            this.tab = name;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', name);
            window.history.replaceState({}, '', url);
        },

        async refreshAll() {
            this.loading = true;
            try {
                const [status, agents, dispo] = await Promise.all([
                    window.axios.get('/api/reports/call-status-stats', { params: this.filters }),
                    window.axios.get('/api/reports/agent-stats', { params: this.filters }),
                    window.axios.get('/api/reports/call-dispo-report', { params: this.filters }),
                ]);
                this.payloads.status = status.data;
                this.payloads.agents = agents.data;
                this.payloads.dispo = dispo.data;
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to load report data.');
            } finally {
                this.loading = false;
            }
        },

        async lookupRecordings(filters = {}) {
            try {
                const res = await window.axios.get('/api/call/recording/lookup', { params: filters });
                this.payloads.recording = res.data;
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to lookup recordings.');
            }
        },
    };
};
</script>
@endpush

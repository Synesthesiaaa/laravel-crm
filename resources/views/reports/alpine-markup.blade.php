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

    <div class="flex gap-2 border-b border-[var(--color-border)]">
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'status' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="tab = 'status'">
            Call Status Stats
        </button>
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'agents' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="tab = 'agents'">
            Agent Performance
        </button>
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'dispo' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="tab = 'dispo'">
            Disposition Report
        </button>
        <button class="px-4 py-2 text-sm font-medium border-b-2 transition-colors"
                :class="tab === 'recording' ? 'border-[var(--color-primary)] text-[var(--color-primary)]' : 'border-transparent text-[var(--color-on-surface-muted)]'"
                @click="tab = 'recording'">
            Recording Browser
        </button>
    </div>

    <div x-show="tab === 'status'">@include('reports.partials.call-status')</div>
    <div x-show="tab === 'agents'">@include('reports.partials.agent-stats')</div>
    <div x-show="tab === 'dispo'">@include('reports.partials.dispo-report')</div>
    <div x-show="tab === 'recording'">@include('reports.partials.recording-browser')</div>
</div>

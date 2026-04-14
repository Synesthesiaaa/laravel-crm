<x-page-header title="Telephony Monitor" description="Real-time telephony health and event feed."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Telephony Monitor' => null]" />

<div x-data="telephonyMonitor()" x-init="init()" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md-card p-4">
            <p class="text-xs text-[var(--color-on-surface-dim)]">Overall Status</p>
            <p class="text-lg font-semibold capitalize" x-text="status">{{ $status }}</p>
        </div>
        <div class="md-card p-4">
            <p class="text-xs text-[var(--color-on-surface-dim)]">Active Calls</p>
            <p class="text-lg font-semibold">{{ $metrics['active_calls'] ?? 0 }}</p>
        </div>
        <div class="md-card p-4">
            <p class="text-xs text-[var(--color-on-surface-dim)]">Stale Calls</p>
            <p class="text-lg font-semibold">{{ $metrics['stale_calls'] ?? 0 }}</p>
        </div>
        <div class="md-card p-4">
            <p class="text-xs text-[var(--color-on-surface-dim)]">Unmatched AMI (24h)</p>
            <p class="text-lg font-semibold">{{ $metrics['unmatched_ami_events_24h'] ?? 0 }}</p>
        </div>
    </div>

    <div class="md-card p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold">Live Telephony Events</h3>
            <select x-model="severityFilter" class="form-select w-40">
                <option value="all">All Severities</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
            </select>
        </div>
        <div class="space-y-2 max-h-80 overflow-auto">
            <template x-if="filteredEvents().length === 0">
                <p class="text-sm text-[var(--color-on-surface-dim)]">No live events yet.</p>
            </template>
            <template x-for="event in filteredEvents()" :key="event.id">
                <div class="border rounded-md p-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold uppercase" x-text="event.severity"></span>
                        <span class="text-xs text-[var(--color-on-surface-dim)]" x-text="event.timestamp"></span>
                    </div>
                    <p class="text-sm font-medium mt-1" x-text="event.message"></p>
                    <p class="text-xs text-[var(--color-on-surface-dim)] mt-1" x-text="event.event_type"></p>
                </div>
            </template>
        </div>
    </div>

    <div class="md-card p-5">
        <h3 class="text-sm font-semibold mb-3">Recent Alerts (24h)</h3>
        <div class="space-y-2 max-h-80 overflow-auto">
            @forelse($recentAlerts as $alert)
                <div class="border rounded-md p-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase">{{ $alert->severity }}</span>
                        <span class="text-xs text-[var(--color-on-surface-dim)]">{{ $alert->created_at?->toDateTimeString() }}</span>
                    </div>
                    <p class="text-sm mt-1">{{ $alert->message }}</p>
                </div>
            @empty
                <p class="text-sm text-[var(--color-on-surface-dim)]">No alerts in the last 24 hours.</p>
            @endforelse
        </div>
    </div>
</div>

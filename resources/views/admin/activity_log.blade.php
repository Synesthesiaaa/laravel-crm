@extends('layouts.app')

@section('title', 'Activity Log - Admin')
@section('header-icon')<x-icon name="document-text" class="w-5 h-5 text-[var(--color-primary)]" />@endsection
@section('header-title', 'Activity Log')

@section('content')
<x-page-header title="Activity Log" description="Realtime administrative activity and configuration changes."
    :breadcrumbs="['Admin' => route('admin.dashboard'), 'Activity Log' => null]" />

<div x-data="activityLogTerminal({
        initialEntries: @js($entries->values()->all()),
        historyUrl: @js(route('admin.activity-log.entries')),
    })" class="activity-log-page space-y-4">
    <section class="md-card p-4 activity-log-filter-card" aria-labelledby="activity-filter-heading">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-[var(--color-action)]">LIVE ACTIVITY STREAM</p>
                <h2 id="activity-filter-heading" class="mt-1 text-base font-semibold text-[var(--color-on-surface)]">Filter activity</h2>
                <p id="activity-filter-help" class="mt-1 text-sm text-[var(--color-on-surface-muted)]">Search the audit stream by actor, action, description, or date.</p>
            </div>
            <div class="activity-log-connection-status flex items-center gap-2 text-xs font-mono" role="status" aria-live="polite" aria-atomic="true">
                <span class="activity-log-connection-dot" :class="connectionClass()"></span>
                <span x-text="connectionLabel()">Polling</span>
            </div>
        </div>

        <form class="activity-log-filters grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3 items-end" aria-describedby="activity-filter-help" @submit.prevent="load()">
            <div class="form-field xl:col-span-2">
                <label class="form-label" for="activity-search">Search</label>
                <input id="activity-search" type="search" class="form-input" maxlength="120"
                       x-model="filters.search" placeholder="Description or resource">
            </div>
            <div class="form-field">
                <label class="form-label" for="activity-actor">Actor</label>
                <select id="activity-actor" class="form-select" x-model="filters.actor_id">
                    <option value="">All actors</option>
                    @foreach($actors as $actor)
                        <option value="{{ $actor->id }}">{{ $actor->full_name ?: $actor->username }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="activity-event">Action</label>
                <select id="activity-event" class="form-select" x-model="filters.event">
                    <option value="">All actions</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                    <option value="login">Login</option>
                    <option value="logout">Logout</option>
                    <option value="retention_run">Retention run</option>
                    <option value="request">Requests</option>
                </select>
            </div>
            <div class="form-field">
                <label class="form-label" for="activity-from">From</label>
                <input id="activity-from" type="date" class="form-input" x-model="filters.from">
            </div>
            <div class="form-field">
                <label class="form-label" for="activity-to">To</label>
                <input id="activity-to" type="date" class="form-input" x-model="filters.to">
            </div>
            <div class="activity-log-filter-actions flex gap-2 sm:col-span-2 xl:col-span-6">
                <button type="submit" class="btn-primary" :disabled="loading" :aria-busy="loading">
                    <x-icon name="funnel" class="w-4 h-4" />
                    <span x-text="loading ? 'Loading...' : 'Apply filters'">Apply filters</span>
                </button>
                <button type="button" class="btn-secondary" @click="resetFilters()" :disabled="loading">Reset</button>
            </div>
        </form>
    </section>

    <section class="activity-terminal" aria-labelledby="activity-terminal-heading" aria-describedby="activity-stream-status">
        <div class="activity-terminal-toolbar">
            <div class="flex items-center gap-2 min-w-0">
                <span class="activity-terminal-prompt">Shell</span>
                <span class="text-[var(--color-on-surface-dim)]">$</span>
                <h2 id="activity-terminal-heading" class="sr-only">Activity stream</h2>
                <p id="activity-stream-status" class="sr-only" role="status" aria-live="polite" aria-atomic="true"
                   x-text="`${entries.length} visible entries. ${paused ? 'Stream paused.' : following ? 'Following newest entries.' : 'Following paused.'}`">
                    Activity stream status
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" class="activity-terminal-button" @click="following = !following" :aria-pressed="following" :aria-label="following ? 'Stop following newest entries' : 'Follow newest entries'">
                    <span x-text="following ? 'Following' : 'Follow'">Follow</span>
                </button>
                <button type="button" class="activity-terminal-button" @click="paused = !paused" :aria-pressed="paused" :aria-label="paused ? 'Resume activity stream' : 'Pause activity stream'">
                    <span x-text="paused ? 'Resume' : 'Pause'">Pause</span>
                </button>
                <button type="button" class="activity-terminal-button" @click="clearBuffer()" aria-label="Clear visible activity entries">Clear</button>
            </div>
        </div>

        <div x-ref="output" class="activity-terminal-output" tabindex="0" role="log" aria-live="polite" aria-relevant="additions text" aria-label="Live activity entries">
            <template x-if="entries.length === 0">
                <div class="activity-terminal-empty">No activity entries match the current filters.</div>
            </template>
            <template x-for="entry in entries" :key="entry.id">
                <article class="activity-terminal-entry" :class="`activity-terminal-entry--${entry.severity}`">
                    <button type="button" class="activity-terminal-line" @click="toggle(entry.id)" :aria-expanded="isExpanded(entry.id)" :aria-controls="`activity-entry-details-${entry.id}`" :aria-label="`${entry.action}: ${entry.description}`">
                        <span class="activity-terminal-time" x-text="formatTime(entry.timestamp)"></span>
                        <span class="activity-terminal-mark" aria-hidden="true">●</span>
                        <span class="activity-terminal-action" x-text="entry.action"></span>
                        <span class="activity-terminal-description" x-text="entry.description"></span>
                        <span class="activity-terminal-actor" x-text="`[${entry.actor}]`"></span>
                        <span class="activity-terminal-severity" x-text="entry.severity || 'info'">info</span>
                    </button>
                    <div x-show="isExpanded(entry.id)" x-cloak class="activity-terminal-details" :id="`activity-entry-details-${entry.id}`" role="region" :aria-label="`Audit details for ${entry.action}`">
                        <div class="activity-terminal-section-title">Audit context</div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div><span class="activity-terminal-key">Actor</span><span x-text="entry.actor"></span></div>
                            <div><span class="activity-terminal-key">Username</span><span x-text="entry.actor_details?.username || 'system'"></span></div>
                            <div><span class="activity-terminal-key">Role</span><span x-text="entry.actor_details?.role || 'system'"></span></div>
                            <div><span class="activity-terminal-key">User ID</span><span x-text="entry.actor_details?.id || '—'"></span></div>
                            <div><span class="activity-terminal-key">Event</span><span x-text="entry.event || entry.action"></span></div>
                            <div><span class="activity-terminal-key">Source</span><span x-text="entry.log_name || 'system'"></span></div>
                            <div><span class="activity-terminal-key">Resource</span><span x-text="entry.resource_type ? `${entry.resource_type} ${entry.resource ?? ''}` : 'system'"></span></div>
                            <div><span class="activity-terminal-key">Resource ID</span><span x-text="entry.resource_id || '—'"></span></div>
                            <div><span class="activity-terminal-key">Severity</span><span x-text="entry.severity"></span></div>
                        </div>
                        <template x-if="entry.request">
                            <div class="activity-terminal-detail-section">
                                <div class="activity-terminal-section-title">Request</div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                                    <div><span class="activity-terminal-key">Method / path</span><span x-text="`${entry.request.method} ${entry.request.path}`"></span></div>
                                    <div><span class="activity-terminal-key">Route</span><span x-text="entry.request.route || 'anonymous route'"></span></div>
                                    <div><span class="activity-terminal-key">Status</span><span x-text="entry.request.status"></span></div>
                                    <div><span class="activity-terminal-key">IP address</span><span x-text="entry.request.ip || '—'"></span></div>
                                    <div class="md:col-span-2"><span class="activity-terminal-key">User agent</span><span x-text="entry.request.user_agent || '—'"></span></div>
                                </div>
                                <template x-if="entry.request.query && Object.keys(entry.request.query).length">
                                    <pre class="activity-terminal-json" x-text="JSON.stringify(entry.request.query, null, 2)"></pre>
                                </template>
                            </div>
                        </template>
                        <template x-if="entry.changes?.diff && Object.keys(entry.changes.diff).length">
                            <div class="activity-terminal-detail-section">
                                <div class="activity-terminal-section-title">Changes</div>
                                <div class="activity-terminal-diff">
                                    <template x-for="[field, change] in Object.entries(entry.changes.diff)" :key="field">
                                        <div class="activity-terminal-diff-row">
                                            <span class="activity-terminal-diff-field" x-text="field"></span>
                                            <span><span class="activity-terminal-key">Before</span><span x-text="formatDetailValue(change.old)"></span></span>
                                            <span><span class="activity-terminal-key">After</span><span x-text="formatDetailValue(change.new)"></span></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                        <div class="activity-terminal-detail-section">
                            <div class="activity-terminal-section-title">Raw sanitized record</div>
                            <pre class="activity-terminal-json" x-text="JSON.stringify(entry, null, 2)"></pre>
                        </div>
                    </div>
                </article>
            </template>
        </div>
        <div class="activity-terminal-footer">
            <span><span x-text="entries.length">0</span> visible entries</span>
            <span class="font-mono">RETENTION: 90D</span>
        </div>
    </section>
</div>
@endsection



@push('scripts')
<script>
window.activityLogTerminal = function (config) {
    return {
        entries: config.initialEntries || [],
        historyUrl: config.historyUrl,
        filters: { actor_id: '', event: '', resource: '', search: '', from: '', to: '' },
        following: true,
        paused: false,
        loading: false,
        connection: 'polling',
        expanded: [],
        lastId: null,
        _unsubscribe: null,
        _pollTimer: null,
        _requestInFlight: false,
        _destroyed: false,
        _initialized: false,
        init() {
            if (this._initialized) return;
            this._initialized = true;
            this.updateLastId();
            this.bindRealtime();
            this.startPolling();
            this.$nextTick(() => this.scrollToBottom());
        },
        bindRealtime() {
            const echo = window.TelephonyEcho;
            if (!echo?.initEcho || !echo.isBroadcastEnabled?.()) {
                this.connection = 'polling';
                return;
            }

            echo.initEcho();
            this._unsubscribe = echo.subscribeActivityLog?.((entry) => {
                this.connection = 'connected';
                this.append(entry);
            }) || null;
        },
        startPolling() {
            this.stopPolling();
            this._pollTimer = window.setInterval(() => this.poll(), 5000);
            void this.poll();
        },
        stopPolling() {
            if (this._pollTimer) window.clearInterval(this._pollTimer);
            this._pollTimer = null;
        },
        async load() {
            if (this._destroyed || this._requestInFlight) return;

            this._requestInFlight = true;
            this.loading = true;
            try {
                const response = await window.axios.get(this.historyUrl, { params: this.queryParams() });
                if (this._destroyed) return;
                this.entries = response.data?.data || [];
                this.updateLastId();
                this.$nextTick(() => this.scrollToBottom());
            } catch (error) {
                if (this._destroyed) return;
                this.connection = 'offline';
            } finally {
                this._requestInFlight = false;
                this.loading = false;
            }
        },
        async poll() {
            if (this._destroyed || this._requestInFlight) return;

            this._requestInFlight = true;
            const echoConnected = window.TelephonyEcho?.isEchoConnected?.();
            if (!echoConnected) {
                this.connection = window.TelephonyEcho?.isBroadcastEnabled?.() ? 'offline' : 'polling';
            } else {
                this.connection = 'connected';
            }

            try {
                const response = await window.axios.get(this.historyUrl, {
                    params: { ...this.queryParams(), since_id: this.lastId || undefined, limit: 100 },
                });
                if (this._destroyed) return;
                (response.data?.data || []).forEach((entry) => this.append(entry, false));
                if (!echoConnected) this.connection = 'polling';
            } catch (error) {
                if (this._destroyed) return;
                if (!echoConnected) this.connection = 'offline';
            } finally {
                this._requestInFlight = false;
            }
        },
        queryParams() {
            return Object.fromEntries(Object.entries(this.filters).filter(([, value]) => value !== ''));
        },
        append(entry, scroll = true) {
            if (!entry?.id || this.entries.some((existing) => Number(existing.id) === Number(entry.id))) return;
            this.entries = [...this.entries, entry].sort((a, b) => Number(a.id) - Number(b.id)).slice(-200);
            this.updateLastId();
            if (scroll && this.following && !this.paused) this.$nextTick(() => this.scrollToBottom());
        },
        updateLastId() {
            this.lastId = this.entries.reduce((last, entry) => Math.max(last, Number(entry.id) || 0), 0) || null;
        },
        resetFilters() {
            this.filters = { actor_id: '', event: '', resource: '', search: '', from: '', to: '' };
            this.load();
        },
        clearBuffer() {
            this.entries = [];
        },
        toggle(id) {
            this.expanded = this.expanded.includes(id) ? this.expanded.filter((item) => item !== id) : [...this.expanded, id];
        },
        isExpanded(id) {
            return this.expanded.includes(id);
        },
        formatTime(timestamp) {
            if (!timestamp) return '--:--:--';
            return new Date(timestamp).toLocaleString([], { dateStyle: 'short', timeStyle: 'medium' });
        },
        formatDetailValue(value) {
            if (value === null || value === undefined || value === '') return '—';
            return typeof value === 'object' ? JSON.stringify(value) : String(value);
        },
        scrollToBottom() {
            if (this.$refs.output && this.following && !this.paused) this.$refs.output.scrollTop = this.$refs.output.scrollHeight;
        },
        connectionLabel() {
            return this.connection === 'connected' ? 'Live' : this.connection === 'offline' ? 'Realtime unavailable' : 'Polling fallback';
        },
        connectionClass() {
            return `activity-log-connection-dot--${this.connection === 'connected' ? 'connected' : this.connection === 'offline' ? 'offline' : 'polling'}`;
        },
        destroy() {
            this._destroyed = true;
            if (typeof this._unsubscribe === 'function') this._unsubscribe();
            this.stopPolling();
            this._unsubscribe = null;
        },
    };
};
</script>
@endpush

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
    <div class="md-card p-4">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <p class="text-xs font-semibold tracking-[0.18em] text-[var(--color-primary)]">LIVE ACTIVITY STREAM</p>
            </div>
            <div class="flex items-center gap-2 text-xs font-mono" aria-live="polite">
                <span class="activity-log-connection-dot" :class="connectionClass()"></span>
                <span x-text="connectionLabel()">Polling</span>
            </div>
        </div>

        <form class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3 items-end" @submit.prevent="load()">
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
            <div class="flex gap-2 sm:col-span-2 xl:col-span-6">
                <button type="submit" class="btn-primary" :disabled="loading">
                    <x-icon name="funnel" class="w-4 h-4" />
                    <span x-text="loading ? 'Loading...' : 'Apply filters'">Apply filters</span>
                </button>
                <button type="button" class="btn-secondary" @click="resetFilters()">Reset</button>
            </div>
        </form>
    </div>

    <section class="activity-terminal" aria-labelledby="activity-terminal-heading">
        <div class="activity-terminal-toolbar">
            <div class="flex items-center gap-2 min-w-0">
                <span class="activity-terminal-prompt">Shell</span>
                <span class="text-[var(--color-on-surface-dim)]">$</span>
                <h2 id="activity-terminal-heading" class="sr-only">Activity stream</h2>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" class="activity-terminal-button" @click="following = !following" :aria-pressed="following">
                    <span x-text="following ? 'Following' : 'Follow'">Follow</span>
                </button>
                <button type="button" class="activity-terminal-button" @click="paused = !paused" :aria-pressed="paused">
                    <span x-text="paused ? 'Resume' : 'Pause'">Pause</span>
                </button>
                <button type="button" class="activity-terminal-button" @click="clearBuffer()">Clear</button>
            </div>
        </div>

        <div x-ref="output" class="activity-terminal-output" tabindex="0" aria-live="polite">
            <template x-if="entries.length === 0">
                <div class="activity-terminal-empty">No activity entries match the current filters.</div>
            </template>
            <template x-for="entry in entries" :key="entry.id">
                <article class="activity-terminal-entry" :class="`activity-terminal-entry--${entry.severity}`">
                    <button type="button" class="activity-terminal-line" @click="toggle(entry.id)" :aria-expanded="isExpanded(entry.id)">
                        <span class="activity-terminal-time" x-text="formatTime(entry.timestamp)"></span>
                        <span class="activity-terminal-mark" aria-hidden="true">●</span>
                        <span class="activity-terminal-action" x-text="entry.action"></span>
                        <span class="activity-terminal-description" x-text="entry.description"></span>
                        <span class="activity-terminal-actor" x-text="`[${entry.actor}]`"></span>
                    </button>
                    <div x-show="isExpanded(entry.id)" x-cloak class="activity-terminal-details">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs">
                            <div><span class="activity-terminal-key">resource</span><span x-text="entry.resource_type ? `${entry.resource_type} ${entry.resource ?? ''}` : 'system'"></span></div>
                            <div><span class="activity-terminal-key">activity_id</span><span x-text="entry.id"></span></div>
                            <div><span class="activity-terminal-key">severity</span><span x-text="entry.severity"></span></div>
                        </div>
                        <pre class="activity-terminal-json" x-text="JSON.stringify(entry.changes, null, 2)"></pre>
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

@push('styles')
<style>
    .activity-log-connection-dot { width: .5rem; height: .5rem; border-radius: 999px; background: var(--color-on-surface-dim); }
    .activity-log-connection-dot--connected { background: var(--color-success); box-shadow: 0 0 0 .2rem var(--color-success-muted); }
    .activity-log-connection-dot--polling { background: var(--color-warning); box-shadow: 0 0 0 .2rem var(--color-warning-muted); }
    .activity-log-connection-dot--offline { background: var(--color-danger); box-shadow: 0 0 0 .2rem var(--color-danger-muted); }
    .activity-terminal { overflow: hidden; border: 1px solid rgba(90, 100, 120, .45); border-radius: .75rem; background: #090c10; color: #d4d7dc; box-shadow: 0 18px 55px rgba(0, 0, 0, .28); }
    .activity-terminal-toolbar, .activity-terminal-footer { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .8rem 1rem; font: 600 .72rem/1.2 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
    .activity-terminal-toolbar { border-bottom: 1px solid rgba(120, 140, 160, .2); background: #11161d; }
    .activity-terminal-footer { border-top: 1px solid rgba(120, 140, 160, .2); color: #687585; }
    .activity-terminal-prompt { color: #66e3a2; }
    .activity-terminal-button { border: 1px solid rgba(120, 140, 160, .3); border-radius: .35rem; padding: .35rem .55rem; color: #b8c2cf; background: rgba(255,255,255,.03); transition: background .15s, color .15s; }
    .activity-terminal-button:hover { color: #fff; background: rgba(255,255,255,.1); }
    .activity-terminal-output { max-height: min(65vh, 42rem); overflow: auto; padding: .8rem 0; font: 500 .78rem/1.65 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; scrollbar-color: #3d4b5a #0b0f14; }
    .activity-terminal-entry { border-left: 2px solid transparent; }
    .activity-terminal-entry:hover { background: rgba(255,255,255,.025); }
    .activity-terminal-entry--success { border-left-color: #3adf91; }
    .activity-terminal-entry--warning { border-left-color: #f7c75f; }
    .activity-terminal-entry--error { border-left-color: #ff6d7e; }
    .activity-terminal-line { display: grid; grid-template-columns: 10.5rem 1rem 6.5rem minmax(0, 1fr) auto; gap: .55rem; width: 100%; padding: .2rem 1rem; text-align: left; color: inherit; }
    .activity-terminal-time, .activity-terminal-actor { color: #718096; }
    .activity-terminal-mark { color: #66e3a2; }
    .activity-terminal-action { color: #8cc8ff; text-transform: uppercase; }
    .activity-terminal-entry--warning .activity-terminal-mark, .activity-terminal-entry--warning .activity-terminal-action { color: #f7c75f; }
    .activity-terminal-entry--error .activity-terminal-mark, .activity-terminal-entry--error .activity-terminal-action { color: #ff6d7e; }
    .activity-terminal-description { overflow: hidden; color: #e6e9ee; text-overflow: ellipsis; white-space: nowrap; }
    .activity-terminal-details { margin: .15rem 1rem .65rem 18.2rem; border-left: 1px solid rgba(120, 140, 160, .3); padding: .45rem .75rem; color: #aab5c2; }
    .activity-terminal-key { display: inline-block; width: 5.5rem; color: #687585; }
    .activity-terminal-json { margin-top: .65rem; overflow-x: auto; color: #a9e8c3; }
    .activity-terminal-empty { padding: 2.5rem 1rem; text-align: center; color: #687585; }
    @media (max-width: 720px) {
        .activity-terminal-line { grid-template-columns: 7.5rem 1rem minmax(0, 1fr); gap: .35rem; padding-inline: .7rem; }
        .activity-terminal-action { grid-column: 3; }
        .activity-terminal-description { grid-column: 3; }
        .activity-terminal-actor { display: none; }
        .activity-terminal-details { margin-left: .7rem; margin-right: .7rem; }
        .activity-terminal-toolbar { align-items: flex-start; flex-direction: column; }
    }
</style>
@endpush

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
        init() {
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
            this._pollTimer = window.setInterval(() => this.poll(), 5000);
            this.poll();
        },
        async load() {
            this.loading = true;
            try {
                const response = await window.axios.get(this.historyUrl, { params: this.queryParams() });
                this.entries = response.data?.data || [];
                this.updateLastId();
                this.$nextTick(() => this.scrollToBottom());
            } catch (error) {
                this.connection = 'offline';
            } finally {
                this.loading = false;
            }
        },
        async poll() {
            const echoConnected = window.TelephonyEcho?.isEchoConnected?.();
            if (echoConnected) {
                this.connection = 'connected';
                return;
            }

            this.connection = window.TelephonyEcho?.isBroadcastEnabled?.() ? 'offline' : 'polling';
            try {
                const response = await window.axios.get(this.historyUrl, {
                    params: { ...this.queryParams(), since_id: this.lastId || undefined, limit: 100 },
                });
                (response.data?.data || []).forEach((entry) => this.append(entry, false));
                this.connection = 'polling';
            } catch (error) {
                this.connection = 'offline';
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
            if (typeof this._unsubscribe === 'function') this._unsubscribe();
            if (this._pollTimer) window.clearInterval(this._pollTimer);
            this._unsubscribe = null;
            this._pollTimer = null;
        },
    };
};
</script>
@endpush

/**
 * Global lead-import progress: persists across navigations and syncs across tabs via localStorage.
 */
const LS_KEY = 'crm_lead_import_track';

export default function registerLeadImportTrackStore(Alpine) {
    const store = {
        /** @type {{ run_id: string, poll_url: string, estimated_rows: number, list_name?: string, list_url?: string, dismiss_url: string } | null} */
        track: null,
        state: null,
        error: null,
        timer: null,
        collapsed: false,
        /** After success, reload once when user dismisses. */
        _reloadOnDismiss: false,

        get panelVisible() {
            return Boolean(this.track);
        },

        get isTerminal() {
            const s = this.state?.status;
            return ['completed', 'failed', 'unknown'].includes(s);
        },

        get recentReversed() {
            const r = this.state?.recent ?? [];
            return [...r].reverse();
        },

        bootstrap() {
            let incoming = null;
            if (typeof window !== 'undefined' && window.__LEAD_IMPORT_TRACK__) {
                incoming = window.__LEAD_IMPORT_TRACK__;
                delete window.__LEAD_IMPORT_TRACK__;
            }
            const fromLs = this.readLs();
            if (!incoming && fromLs?.run_id && fromLs?.poll_url) {
                incoming = fromLs;
            }
            if (incoming?.run_id && incoming?.poll_url) {
                this.applyTrack(incoming);
            }

            if (typeof window !== 'undefined') {
                window.addEventListener('storage', (e) => {
                    if (e.key !== LS_KEY) {
                        return;
                    }
                    if (!e.newValue) {
                        this.stopPolling();
                        this.track = null;
                        this.state = null;
                        this.error = null;
                        this.collapsed = false;
                        return;
                    }
                    try {
                        const parsed = JSON.parse(e.newValue);
                        if (!parsed?.run_id || !parsed?.poll_url) {
                            return;
                        }
                        if (parsed.run_id === this.track?.run_id) {
                            return;
                        }
                        this.applyTrack(parsed);
                    } catch (_) {
                        /* ignore */
                    }
                });
            }
        },

        readLs() {
            try {
                const raw = localStorage.getItem(LS_KEY);
                return raw ? JSON.parse(raw) : null;
            } catch (_) {
                return null;
            }
        },

        persistLs() {
            if (this.track) {
                localStorage.setItem(LS_KEY, JSON.stringify(this.track));
            } else {
                localStorage.removeItem(LS_KEY);
            }
        },

        applyTrack(t) {
            this.stopPolling();
            this.track = t;
            this.state = null;
            this.error = null;
            this.collapsed = false;
            this.persistLs();
            this.startPolling();
        },

        startPolling() {
            if (!this.track?.poll_url || this.timer) {
                return;
            }
            this.tick();
            this.timer = setInterval(() => this.tick(), 1500);
        },

        stopPolling() {
            if (this.timer) {
                clearInterval(this.timer);
                this.timer = null;
            }
        },

        async tick() {
            if (!this.track?.poll_url) {
                return;
            }
            try {
                const r = await fetch(this.track.poll_url, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (r.status === 429) {
                    this.error = 'Too many requests; retrying…';

                    return;
                }
                if (!r.ok) {
                    if ([403, 404, 422].includes(r.status)) {
                        await this.clearStaleImportUi({ stale: true });

                        return;
                    }
                    this.error = `HTTP ${r.status}`;

                    return;
                }
                const data = await r.json();
                const s = data?.status;
                if (s === 'unknown') {
                    this.stopPolling();
                    await this.clearStaleImportUi({ stale: true });

                    return;
                }
                this.state = data;
                this.error = null;

                if (s === 'completed' || s === 'failed') {
                    this.stopPolling();
                    if (s === 'completed') {
                        this._reloadOnDismiss = true;
                    }
                    this.collapsed = false;
                }
            } catch (e) {
                this.error = e?.message || 'Network error';
            }
        },

        /**
         * Remove phantom progress (expired cache, stale localStorage, 403 on poll).
         * Optionally POST `stale: true` so Laravel forgets `lead_import_track`
         * and the next full page load does not re-open the panel.
         */
        async clearStaleImportUi({ stale = false } = {}) {
            this.stopPolling();
            const url = this.track?.dismiss_url;
            const runId = this.track?.run_id;
            this.track = null;
            this.state = null;
            this.error = null;
            this.collapsed = false;
            this._reloadOnDismiss = false;
            this.persistLs();
            if (stale && url) {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                try {
                    await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ run_id: runId, stale: true }),
                    });
                } catch (_) {
                    /* ignore */
                }
            }
        },

        statusLabel() {
            const st = this.state?.status;
            if (!st) {
                return 'Starting…';
            }
            const map = {
                queued: 'Waiting in queue…',
                processing: 'Importing rows…',
                completed: 'Import finished',
                failed: 'Import failed',
                unknown: 'Progress unavailable',
            };
            return map[st] || st;
        },

        percentDisplay() {
            if (!this.state) {
                return null;
            }
            if (this.state.percent != null) {
                return this.state.percent;
            }
            const est = Number(this.track?.estimated_rows ?? 0);
            if (est > 0 && this.state.rows_processed != null) {
                return Math.min(100, Math.round((this.state.rows_processed / est) * 100));
            }
            return null;
        },

        isIndeterminate() {
            if (!this.state) {
                return true;
            }
            if (['queued', 'processing'].includes(this.state.status) && this.percentDisplay() == null) {
                return true;
            }
            return false;
        },

        toggleCollapsed() {
            this.collapsed = !this.collapsed;
        },

        async dismiss() {
            this.stopPolling();
            const url = this.track?.dismiss_url;
            const runId = this.track?.run_id;
            if (url) {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                try {
                    await fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ run_id: runId }),
                    });
                } catch (_) {
                    /* still clear client state */
                }
            }
            const shouldReload = this._reloadOnDismiss && this.state?.status === 'completed';
            this.track = null;
            this.state = null;
            this.error = null;
            this.collapsed = false;
            this._reloadOnDismiss = false;
            this.persistLs();
            if (shouldReload) {
                window.location.reload();
            }
        },
    };

    Alpine.store('leadImport', store);

    document.addEventListener('alpine:init', () => {
        store.bootstrap();
    });
}

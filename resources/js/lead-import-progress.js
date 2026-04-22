/**
 * Live import progress on the lead list show page (polls JSON from Laravel).
 */
export default function registerLeadImportProgress(Alpine) {
    Alpine.data('leadImportProgress', (cfg) => ({
        cfg: typeof cfg === 'string' ? JSON.parse(cfg) : cfg,
        state: null,
        error: null,
        timer: null,

        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1500);
        },

        async tick() {
            try {
                const r = await fetch(this.cfg.pollUrl, {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!r.ok) {
                    this.error = `HTTP ${r.status}`;
                    return;
                }
                this.state = await r.json();
                this.error = null;

                const s = this.state?.status;
                if (s === 'completed' || s === 'failed' || s === 'unknown') {
                    if (this.timer) {
                        clearInterval(this.timer);
                        this.timer = null;
                    }
                    if (s === 'completed') {
                        setTimeout(() => window.location.reload(), 1200);
                    }
                }
            } catch (e) {
                this.error = e?.message || 'Network error';
            }
        },

        statusLabel() {
            const s = this.state?.status;
            if (!s) return 'Starting…';
            const map = {
                queued: 'Waiting in queue…',
                processing: 'Importing rows…',
                completed: 'Import finished',
                failed: 'Import failed',
                unknown: 'Progress unavailable',
            };
            return map[s] || s;
        },

        percentDisplay() {
            if (!this.state) return null;
            if (this.state.percent != null) return this.state.percent;
            if (this.cfg.estimatedRows > 0 && this.state.rows_processed != null) {
                return Math.min(100, Math.round((this.state.rows_processed / this.cfg.estimatedRows) * 100));
            }
            return null;
        },

        isIndeterminate() {
            if (!this.state) return true;
            if (['queued', 'processing'].includes(this.state.status) && this.percentDisplay() == null) {
                return true;
            }
            return false;
        },
    }));
}

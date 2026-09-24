document.addEventListener('alpine:init', () => {
    window.Alpine.data('attendanceRealtimeTable', (options = {}) => {
        const timeFormatter = new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });

        return {
        endpoint: options.endpoint || '/api/attendance/realtime',
        pollSeconds: Math.max(5, Number(options.pollSeconds) || 15),
        maxSessionHours: Math.max(1, Number(options.maxSessionHours) || 24),
        sessions: [],
        stats: {
            online: 0,
            available: 0,
            away: 0,
            longest_session_seconds: 0,
        },
        generatedAt: null,
        loading: true,
        refreshing: false,
        error: '',
        timer: null,
        clockTimer: null,
        now: Date.now(),
        requestInFlight: false,
        visibilityHandler: null,

        init() {
            this.refresh();
            this.timer = window.setInterval(() => {
                if (!document.hidden) this.refresh();
            }, this.pollSeconds * 1000);
            this.clockTimer = window.setInterval(() => {
                if (!document.hidden && this.sessions.length > 0) {
                    this.now = Date.now();
                }
            }, 1000);
            this.visibilityHandler = () => {
                if (document.hidden) return;
                this.now = Date.now();
                this.refresh();
            };
            document.addEventListener('visibilitychange', this.visibilityHandler);
        },

        destroy() {
            if (this.timer) window.clearInterval(this.timer);
            if (this.clockTimer) window.clearInterval(this.clockTimer);
            if (this.visibilityHandler) {
                document.removeEventListener('visibilitychange', this.visibilityHandler);
            }
            this.timer = null;
            this.clockTimer = null;
            this.visibilityHandler = null;
        },

        async refresh() {
            if (this.requestInFlight) return;

            this.requestInFlight = true;
            this.refreshing = this.sessions.length > 0;
            if (this.sessions.length === 0) this.loading = true;

            try {
                const response = await window.axios.get(this.endpoint);
                const data = response?.data ?? {};
                if (data.success !== true || !Array.isArray(data.sessions)) {
                    throw new Error('Invalid realtime attendance response');
                }

                this.sessions = data.sessions;
                this.stats = {
                    ...this.stats,
                    ...(data.stats || {}),
                };
                this.generatedAt = data.generated_at || null;
                this.maxSessionHours = Number(data.max_session_hours) || this.maxSessionHours;
                this.error = '';
            } catch (error) {
                this.error = this.sessions.length
                    ? 'Could not refresh the latest attendance state. Showing the last successful snapshot.'
                    : 'Realtime attendance is temporarily unavailable.';
                console.warn('[attendance-realtime]', error);
            } finally {
                this.loading = false;
                this.refreshing = false;
                this.requestInFlight = false;
            }
        },

        durationSince(iso, fallbackSeconds = 0) {
            if (!iso) return Number(fallbackSeconds) || 0;
            const startedAt = Date.parse(iso);
            if (!Number.isFinite(startedAt)) return Number(fallbackSeconds) || 0;
            return Math.max(0, Math.floor((this.now - startedAt) / 1000));
        },

        formatDuration(seconds) {
            const total = Math.max(0, Number(seconds) || 0);
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);
            const secs = total % 60;

            return [hours, minutes, secs]
                .map((part) => String(part).padStart(2, '0'))
                .join(':');
        },

        formatCompactDuration(seconds) {
            const total = Math.max(0, Number(seconds) || 0);
            const hours = Math.floor(total / 3600);
            const minutes = Math.floor((total % 3600) / 60);

            if (hours > 0) return hours + 'h ' + minutes + 'm';
            return minutes + 'm';
        },

        formatTime(iso) {
            if (!iso) return '—';
            const date = new Date(iso);
            if (Number.isNaN(date.getTime())) return '—';
            return timeFormatter.format(date);
        },

        updatedLabel() {
            if (!this.generatedAt) return 'Waiting for first refresh';
            return 'Updated ' + this.formatTime(this.generatedAt);
        },
        };
    });
});

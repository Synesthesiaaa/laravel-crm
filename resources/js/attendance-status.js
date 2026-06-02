document.addEventListener('alpine:init', () => {
    window.Alpine.data('attendanceStatusPanel', () => ({
        open: null,
        types: [],
        loading: false,
        ready: false,
        async init() {
            await this.refresh();
            this.ready = true;
        },
        formatStarted(iso) {
            if (!iso) {
                return '—';
            }
            try {
                const d = new Date(iso);

                return d.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            } catch {
                return iso;
            }
        },
        async refresh() {
            try {
                const { data } = await window.axios.get('/api/attendance/current');
                if (data.success) {
                    this.open = data.open;
                    if (Array.isArray(data.types) && data.types.length) {
                        this.types = data.types;
                    }
                }
            } catch (e) {
                console.warn('[attendance]', e);
            }
        },
        async start(code) {
            this.loading = true;
            try {
                await window.axios.post('/api/attendance/start', { code });
                window.Alpine?.store('toast')?.success?.('Status started.');
                await this.refresh();
                window.location.reload();
            } catch (e) {
                const msg =
                    e.response?.data?.message ||
                    e.response?.data?.errors?.code?.[0] ||
                    'Could not start status.';
                window.Alpine?.store('toast')?.error?.(msg);
            } finally {
                this.loading = false;
            }
        },
        async end() {
            this.loading = true;
            try {
                await window.axios.post('/api/attendance/end', {});
                window.Alpine?.store('toast')?.success?.('Status ended.');
                await this.refresh();
                window.location.reload();
            } catch (e) {
                const msg =
                    e.response?.data?.message ||
                    e.response?.data?.errors?.status?.[0] ||
                    'Could not end status.';
                window.Alpine?.store('toast')?.error?.(msg);
            } finally {
                this.loading = false;
            }
        },
    }));
});

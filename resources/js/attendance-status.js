document.addEventListener('alpine:init', () => {
    window.Alpine.data('attendanceStatusPanel', (options = {}) => ({
        open: null,
        types: [],
        loading: false,
        ready: false,
        error: null,
        pendingAction: null,
        timeZone: options.timeZone || undefined,
        endpoints: {
            current: options.currentUrl || '/api/attendance/current',
            start: options.startUrl || '/api/attendance/start',
            end: options.endUrl || '/api/attendance/end',
        },
        async init() {
            try {
                await this.refresh();
            } finally {
                this.ready = true;
            }
        },
        formatStarted(iso) {
            if (!iso) {
                return '—';
            }
            try {
                const d = new Date(iso);
                const formatOptions = {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                };
                if (this.timeZone) {
                    formatOptions.timeZone = this.timeZone;
                }

                return d.toLocaleString(undefined, formatOptions);
            } catch {
                return iso;
            }
        },
        errorMessage(error, field, fallback) {
            return (
                error?.response?.data?.message ||
                error?.response?.data?.errors?.[field]?.[0] ||
                fallback
            );
        },
        async refresh({ notify = false } = {}) {
            try {
                const { data } = await window.axios.get(this.endpoints.current);
                if (!data?.success) {
                    throw new Error('Attendance status response was unsuccessful.');
                }

                this.open = data.open ?? null;
                this.types = Array.isArray(data.types) ? data.types : [];
                this.error = null;

                return true;
            } catch (error) {
                this.error = 'Could not load attendance statuses. Please try again.';
                if (notify) {
                    window.Alpine?.store('toast')?.error?.(this.error);
                }
                console.warn('[attendance]', error);

                return false;
            }
        },
        async retry() {
            if (this.loading) {
                return;
            }

            this.loading = true;
            try {
                await this.refresh({ notify: true });
            } finally {
                this.loading = false;
            }
        },
        async refreshPage() {
            try {
                return (await window.crmSoftNav?.refresh?.()) === true;
            } catch (error) {
                console.warn('[attendance] page refresh failed', error);

                return false;
            }
        },
        async runAction(action, code = null) {
            if (this.loading) {
                return;
            }

            this.loading = true;
            this.pendingAction = action;
            this.error = null;

            const isStart = action === 'start';
            const endpoint = isStart ? this.endpoints.start : this.endpoints.end;
            const payload = isStart ? { code } : {};

            try {
                const { data } = await window.axios.post(endpoint, payload);
                if (!data?.success) {
                    throw new Error('Attendance status action was unsuccessful.');
                }

                if (isStart) {
                    const selectedType = this.types.find((type) => type.code === code);
                    this.open = {
                        id: data.log?.id ?? null,
                        code,
                        label: data.log?.status_label || selectedType?.label || code,
                        started_at: data.log?.event_time ?? null,
                    };
                } else {
                    this.open = null;
                }

                window.dispatchEvent(new CustomEvent('attendance-updated', {
                    detail: { action, log: data.log ?? null },
                }));
                window.Alpine?.store('toast')?.success?.(isStart ? 'Status started.' : 'Status ended.');

                const pageRefreshed = await this.refreshPage();
                if (!pageRefreshed) {
                    await this.refresh();
                }
            } catch (error) {
                const field = isStart ? 'code' : 'status';
                const fallback = isStart ? 'Could not start status.' : 'Could not end status.';
                window.Alpine?.store('toast')?.error?.(this.errorMessage(error, field, fallback));
            } finally {
                this.loading = false;
                this.pendingAction = null;
            }
        },
        start(code) {
            return this.runAction('start', code);
        },
        end() {
            return this.runAction('end');
        },
    }));
});

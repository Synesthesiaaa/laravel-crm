export function importErrorMessage(error) {
    const status = error?.response?.status;
    if (status === 422) return error.response.data?.errors?.csv?.[0] || 'Check the CSV file and try again.';
    if (status === 401 || status === 419) return 'Your session expired. Reload the page and sign in again.';
    if (status === 403) return 'Super Admin access is required. Reload the page to check your access.';
    if (status === 404) return 'This import is no longer available. Reload the page to see recent imports.';
    if (status === 409) return 'This import is no longer ready to start. Refresh its status or upload a new CSV.';
    if (status === 413) return 'The CSV exceeds the server upload limit. Use a smaller file or contact your administrator.';
    if (status === 429) return 'Too many uploads. Please wait a minute before trying again.';
    return 'Could not reach the import service. Your last progress is kept; retry to refresh it.';
}

export function createUserImportPanel(config, dependencies = {}) {
    const http = dependencies.http ?? globalThis.window?.axios;
    const schedule = dependencies.schedule ?? setTimeout;
    const cancel = dependencies.cancel ?? clearTimeout;

    return {
        runs: config.runs || [], run: null, busy: false, error: '', allowPrivileged: false,
        uploading: false, uploadPercent: 0, pollFailures: 0,
        _alive: true, _timer: null, _controller: null, _sequence: 0, _visibility: null,
        init() {
            this.run = this.runs[0] || null;
            this._visibility = () => { if (!document.hidden && this.run?.active) this.poll(); };
            document.addEventListener('visibilitychange', this._visibility);
            this.planPoll();
        },
        destroy() {
            this._alive = false;
            this._sequence++;
            cancel(this._timer);
            this._controller?.abort();
            if (this._visibility) document.removeEventListener('visibilitychange', this._visibility);
        },
        applyRun(value) {
            this.run = value;
            this.runs = [value, ...this.runs.filter(item => item.id !== value.id)].slice(0, 5);
        },
        beginRequest() {
            cancel(this._timer);
            this._controller?.abort();
            this._controller = new AbortController();
            return { sequence: ++this._sequence, signal: this._controller.signal };
        },
        planPoll() {
            cancel(this._timer);
            if (this._alive && this.run?.active) {
                this._timer = schedule(() => this.poll(), Math.min(30000, 2500 * (2 ** this.pollFailures)));
            }
        },
        async selectRun(value) {
            if (this.busy) return;
            this._sequence++;
            this.applyRun(value);
            this.error = '';
            await this.poll(true);
        },
        async poll(force = false) {
            if (!this._alive || this.busy || !this.run || (!force && !this.run.active)) return;
            if (!force && globalThis.document?.hidden) { this.planPoll(); return; }
            const { sequence, signal } = this.beginRequest();
            try {
                const response = await http.get(this.run.urls.status, { signal, headers: { Accept: 'application/json' } });
                if (!this._alive || sequence !== this._sequence) return;
                this.applyRun(response.data);
                this.error = '';
                this.pollFailures = 0;
            } catch (error) {
                if (!this._alive || sequence !== this._sequence || signal.aborted) return;
                this.error = importErrorMessage(error);
                this.pollFailures = Math.min(4, this.pollFailures + 1);
                if ([401, 403, 404, 419].includes(error?.response?.status)) return;
            }
            this.planPoll();
        },
        async upload(form) {
            if (this.busy) return;
            const input = form.querySelector('input[type=file]');
            const file = input?.files?.[0];
            if (!file) { this.error = 'Choose a CSV file first.'; return; }
            if (file.size > (config.maxBytes ?? 20 * 1024 * 1024)) { this.error = `Choose a CSV file no larger than ${config.maxLabel || '20 MB'}.`; return; }
            const data = new FormData(form);
            data.set('allow_privileged_roles', this.allowPrivileged ? '1' : '0');
            this.uploading = true;
            this.uploadPercent = 0;
            await this.sendRequest(() => http.post(config.uploadUrl, data, {
                signal: this._controller.signal,
                headers: { Accept: 'application/json' },
                onUploadProgress: progress => { this.uploadPercent = progress.total ? Math.round(progress.loaded / progress.total * 100) : 0; },
            }));
            this.uploading = false;
            if (!this.error) input.value = '';
        },
        async startImport() {
            if (!this.run?.can_start || this.busy) return;
            await this.sendRequest(() => http.post(this.run.urls.start, {}, { signal: this._controller.signal, headers: { Accept: 'application/json' } }));
        },
        async cancelImport() {
            if (!this.run?.can_cancel || this.busy) return;
            await this.sendRequest(() => http.delete(this.run.urls.cancel, { signal: this._controller.signal, headers: { Accept: 'application/json' } }));
        },
        async sendRequest(operation) {
            this.busy = true;
            this.error = '';
            const { sequence, signal } = this.beginRequest();
            try {
                const response = await operation();
                if (this._alive && sequence === this._sequence) this.applyRun(response.data);
            } catch (error) {
                if (this._alive && !signal.aborted && sequence === this._sequence) this.error = importErrorMessage(error);
            } finally {
                if (this._alive && sequence === this._sequence) {
                    this.busy = false;
                    this.planPoll();
                }
            }
        },
        number(value) { return Number(value || 0).toLocaleString(); },
        statusLabel(value = this.run) {
            if (!value) return '';
            if (value.status === 'queued') return value.phase === 'preview' ? 'Preview queued' : 'Import queued';
            if (value.status === 'processing') return value.phase === 'preview' ? 'Checking CSV' : 'Importing users';
            return ({ ready: 'Preview ready', completed: 'Import complete', cancelled: 'Cancelled', expired: 'Expired', failed: 'Import stopped' })[value.status] || 'Checking status';
        },
        statusMessage() {
            if (!this.run) return '';
            if (this.run.message) return this.run.message;
            if (this.run.waiting) return 'Waiting for background processing. Contact your administrator if this does not start.';
            if (this.run.status === 'queued') return 'Your file is queued. You can leave this page and return to check progress.';
            if (this.run.status === 'processing') return this.run.phase === 'preview' ? 'Checking every row. No accounts are created during preview.' : 'Creating eligible accounts in the background. Existing accounts are kept.';
            if (this.run.status === 'ready') return this.run.accepted > 0 ? 'Review the counts, then import the eligible users. Rejected rows will be left out.' : 'No eligible users to import. Review the issues or upload a corrected file.';
            if (this.run.status === 'completed') return 'Finished. Refresh the user list to see the accounts created.';
            return '';
        },
    };
}

if (typeof window !== 'undefined') window.userImportPanel = config => createUserImportPanel(config);

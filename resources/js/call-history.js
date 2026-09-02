const DEFAULT_FILTERS = {
    start_date: '',
    end_date: '',
    agent: '',
    phone: '',
    lead_id: '',
    status: '',
    disposition: '',
    vicidial_campaign: '',
    direction: '',
    sort: 'called_at',
    dir: 'desc',
    page: 1,
    per_page: 15,
};

window.callHistoryPage = function callHistoryPage(config = {}) {
    return {
        campaign: config.campaign || '',
        personal: config.personal === true,
        filters: { ...DEFAULT_FILTERS, ...(config.initialFilters || {}) },
        records: [],
        filterOptions: { agents: [], statuses: [], dispositions: {}, campaigns: [] },
        pagination: { current_page: 1, last_page: 1, total: 0, from: null, to: null, has_more_pages: false },
        sourceHealth: {},
        state: 'loading',
        message: null,
        loading: false,
        refreshing: false,
        requestController: null,
        requestNumber: 0,
        textFilterTimer: null,
        columns: [
            { label: 'Date/Time', key: 'called_at', sortable: true },
            { label: 'Agent', key: 'agent', sortable: true },
            { label: 'Phone', key: 'phone', sortable: false },
            { label: 'Status', key: 'status', sortable: true },
            { label: 'Disposition', key: 'disposition', sortable: false },
            { label: 'Duration', key: 'duration', sortable: true },
            { label: 'Campaign', key: 'vicidial_campaign', sortable: true },
            { label: 'Details', key: 'details', sortable: false },
        ],

        get mappedCampaignCount() {
            return (this.filterOptions.campaigns || []).length;
        },

        get healthStatus() {
            return this.sourceHealth.status || 'stale';
        },

        get healthLabel() {
            if (this.healthStatus === 'healthy') return 'Source healthy';
            if (this.healthStatus === 'stale') return 'Showing stale data';
            return 'Source unavailable';
        },

        get healthMessage() {
            return this.sourceHealth.last_error_message || this.message;
        },

        get syncDetailLabel() {
            if (this.sourceHealth.last_successful_sync_at) {
                return `Last synchronized ${this.formatDate(this.sourceHealth.last_successful_sync_at)}`;
            }
            if (this.sourceHealth.sync_status === 'running') return 'Synchronization in progress';
            if (this.sourceHealth.sync_status === 'failed') return 'Synchronization unavailable';
            return 'Awaiting first synchronization';
        },

        get paginationLabel() {
            if (!this.pagination.total) return '0 calls';
            return `${this.pagination.from}–${this.pagination.to} of ${this.pagination.total} calls`;
        },

        async init() {
            await this.load();
        },

        async load(page = null) {
            if (page !== null) this.filters.page = page;
            if (this.requestController) this.requestController.abort();

            const controller = new AbortController();
            const requestNumber = ++this.requestNumber;
            this.requestController = controller;
            this.loading = true;
            this.message = null;

            const params = { campaign: this.campaign, per_page: this.filters.per_page || 15 };
            Object.entries(this.filters).forEach(([key, value]) => {
                if (value !== '' && value !== null && value !== undefined) params[key] = value;
            });

            try {
                const response = await window.axios.get('/api/call-history', {
                    params,
                    signal: controller.signal,
                    validateStatus: (status) => (status >= 200 && status < 300) || status === 503,
                });
                if (requestNumber !== this.requestNumber) return;
                const data = response.data || {};
                this.records = Array.isArray(data.data) ? data.data : [];
                this.pagination = data.pagination || this.pagination;
                this.filterOptions = data.filters?.available || this.filterOptions;
                this.sourceHealth = data.source_health || {};
                this.state = data.success === false ? 'unavailable' : (data.state || (this.records.length ? 'data' : 'confirmed_empty'));
                this.message = data.message || null;
            } catch (error) {
                if (error?.name === 'CanceledError' || error?.code === 'ERR_CANCELED' || window.axios.isCancel?.(error)) return;
                if (requestNumber !== this.requestNumber) return;
                this.state = 'unavailable';
                this.message = 'Call History could not be loaded. Please retry.';
            } finally {
                if (requestNumber === this.requestNumber) {
                    this.loading = false;
                    this.requestController = null;
                }
            }
        },

        queueTextFilter() {
            window.clearTimeout(this.textFilterTimer);
            this.textFilterTimer = window.setTimeout(() => this.applyFilters(), 450);
        },

        applyFilters() {
            this.filters.page = 1;
            return this.load();
        },

        clearFilters() {
            const perPage = this.filters.per_page;
            this.filters = { ...DEFAULT_FILTERS, per_page: perPage };
            return this.load();
        },

        setSort(column) {
            if (this.filters.sort === column) {
                this.filters.dir = this.filters.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.filters.sort = column;
                this.filters.dir = 'asc';
            }
            return this.load(1);
        },

        async refresh() {
            if (this.refreshing) return;
            this.refreshing = true;
            try {
                await window.axios.post('/api/call-history/refresh', {}, { params: { campaign: this.campaign } });
                this.state = this.records.length ? 'stale' : 'syncing';
                await this.pollRefresh();
                await this.load();
            } catch (error) {
                this.message = error?.response?.data?.message || 'Refresh could not be queued. Please retry.';
            } finally {
                this.refreshing = false;
            }
        },

        async pollRefresh() {
            for (let attempt = 0; attempt < 8; attempt += 1) {
                await new Promise((resolve) => window.setTimeout(resolve, attempt === 0 ? 600 : 1500));
                try {
                    const { data } = await window.axios.get('/api/call-history/status', { params: { campaign: this.campaign } });
                    this.sourceHealth = data.source_health || this.sourceHealth;
                    if (['healthy', 'failed'].includes(this.sourceHealth.sync_status)) return;
                } catch (_) {
                    return;
                }
            }
        },

        goToPage(page) {
            if (page < 1 || (this.pagination.last_page && page > this.pagination.last_page)) return;
            return this.load(page);
        },

        formatDate(value) {
            if (!value) return '—';
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return '—';
            return date.toLocaleString([], { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
        },

        formatDuration(value) {
            if (value === null || value === undefined || value === '') return '—';
            const seconds = Math.max(0, Number(value) || 0);
            if (seconds >= 3600) {
                return [Math.floor(seconds / 3600), Math.floor((seconds % 3600) / 60), seconds % 60]
                    .map((part) => String(part).padStart(2, '0')).join(':');
            }
            return [Math.floor(seconds / 60), seconds % 60]
                .map((part) => String(part).padStart(2, '0')).join(':');
        },

        statusBadge(status) {
            switch (String(status || '').toUpperCase()) {
                case 'SALE': return 'badge-active';
                case 'NEW':
                case 'QUEUE':
                case 'INCALL':
                case 'INQUEUE': return 'badge-pending';
                case 'DROP':
                case 'ABANDON':
                case 'NAN': return 'badge-error';
                default: return 'badge-inactive';
            }
        },
    };
};

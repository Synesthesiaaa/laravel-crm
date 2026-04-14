window.telephonyReports = function () {
    return {
        tab: 'status',
        loading: false,
        filters: {
            campaigns: '---ALL---',
            query_date: new Date().toISOString().slice(0, 10),
            end_date: new Date().toISOString().slice(0, 10),
        },
        recordingFilters: {
            agent_user: '',
            lead_id: '',
            date: new Date().toISOString().slice(0, 10),
        },
        payloads: {
            status: null,
            agents: null,
            dispo: null,
            recording: null,
        },

        init() {
            this.refreshAll();
        },

        async refreshAll() {
            this.loading = true;
            try {
                const [status, agents, dispo] = await Promise.all([
                    window.axios.get('/api/reports/call-status-stats', { params: this.filters }),
                    window.axios.get('/api/reports/agent-stats', { params: this.filters }),
                    window.axios.get('/api/reports/call-dispo-report', { params: this.filters }),
                ]);
                this.payloads.status = status.data;
                this.payloads.agents = agents.data;
                this.payloads.dispo = dispo.data;
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to load report data.');
            } finally {
                this.loading = false;
            }
        },

        async lookupRecordings(filters = {}) {
            try {
                const res = await window.axios.get('/api/call/recording/lookup', { params: filters });
                this.payloads.recording = res.data;
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to lookup recordings.');
            }
        },
    };
};

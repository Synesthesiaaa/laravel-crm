/**
 * Alpine.js component definitions
 * Registered globally via window.* for inline x-data usage in Blade templates
 */

// Notification dropdown component
window.notificationDropdown = function() {
    return {
        open: false,
        items: [],
        unread: 0,
        loaded: false,
        toggle() {
            this.open = !this.open;
            if (this.open && !this.loaded) this.load();
        },
        async load() {
            try {
                const res = await window.axios.get('/api/notifications');
                this.items  = res.data.items  ?? [];
                this.unread = res.data.unread ?? 0;
                this.loaded = true;
            } catch {
                this.items = [];
            }
        },
        async markAllRead() {
            try {
                await window.axios.post('/api/notifications/read-all');
                this.items  = this.items.map(n => ({ ...n, read: true }));
                this.unread = 0;
            } catch {}
        },
    };
};

// Global search component
window.globalSearch = function() {
    return {
        query: '',
        results: [],
        loading: false,
        focused: -1,
        async search() {
            const q = this.query.trim();
            if (q.length < 2) { this.results = []; return; }
            this.loading = true;
            try {
                const res = await window.axios.get('/api/search', { params: { q } });
                this.results = res.data.groups ?? [];
            } catch {
                this.results = [];
            } finally {
                this.loading = false;
            }
        },
        useRecent(q) {
            this.query = q;
            this.search();
        },
        focusNext() {
            const total = this.results.reduce((a, g) => a + g.items.length, 0);
            this.focused = (this.focused + 1) % total;
        },
        focusPrev() {
            const total = this.results.reduce((a, g) => a + g.items.length, 0);
            this.focused = (this.focused - 1 + total) % total;
        },
    };
};

// Confirm-then-submit helper (use with x-data="confirmDelete(...)")
window.confirmDelete = function(message = 'Are you sure you want to delete this?') {
    return {
        async submit(formEl) {
            const ok = await Alpine.store('confirm').ask('Confirm deletion', message, {
                confirmText: 'Delete',
                cancelText:  'Cancel',
                variant:     'danger',
            });
            if (ok) formEl.submit();
        },
    };
};

// Inline edit row toggle (replaces raw onclick handlers)
window.inlineEdit = function(rowId) {
    return {
        open: false,
        toggle() {
            this.open = !this.open;
            const row = document.getElementById(rowId);
            if (row) row.classList.toggle('hidden', !this.open);
        },
    };
};

// Stat card with trend
window.statCard = function(value, previous) {
    return {
        value,
        previous,
        get trend() {
            if (!previous || previous === 0) return 0;
            return ((value - previous) / previous * 100).toFixed(1);
        },
        get trendUp() { return this.trend > 0; },
        get trendDown() { return this.trend < 0; },
    };
};

// Click-to-call widget component
window.clickToCall = function() {
    return {
        phoneNumber: '',
        leadId: null,
        open: false,
        show(number, leadId = null) {
            this.phoneNumber = number;
            this.leadId = leadId;
            this.open = true;
        },
        async dial() {
            if (!this.phoneNumber) return;
            const store = Alpine.store('call');
            store.state = 'ringing';
            store.number = this.phoneNumber;
            this.open = false;
            try {
                // POST to Laravel: creates CallSession + AMI originates to agent's SIP extension.
                // SIP.js (TelephonyCore) will auto-answer the incoming INVITE from Asterisk.
                const res = await window.axios.post('/api/call/dial', {
                    phone_number: this.phoneNumber,
                    lead_id: this.leadId,
                });
                if (res.data.session_id) store.setSessionId(res.data.session_id);
                // State transitions are driven by SIP.js events + Reverb broadcasts;
                // do not hard-code 'connected' here.
            } catch (e) {
                store.state = 'idle';
                const errMsg = e.response?.data?.error?.message
                    || e.response?.data?.message
                    || 'Failed to originate call';
                Alpine.store('toast').error(errMsg);
            }
        },
        async hangup() {
            // Delegate to TelephonyCore which handles SIP BYE + API notification
            if (window.TelephonyCore?.hasActiveCall()) {
                await window.TelephonyCore.hangup();
            } else {
                // Fallback: API-only hangup (e.g. SIP not registered but session exists)
                const store = Alpine.store('call');
                store.stopTimer();
                try {
                    await window.axios.post('/api/call/hangup', { session_id: store.sessionId });
                } catch {
                    Alpine.store('toast').warning('Call ended locally.');
                }
                store.state = 'wrapup';
            }
        },
        toggleMute() {
            Alpine.store('call').toggleMuteWebRTC();
        },
        async toggleHold() {
            await Alpine.store('call').toggleHoldWebRTC();
        },
    };
};

// Table bulk actions component
window.bulkActions = function() {
    return {
        selected: [],
        allSelected: false,
        toggleAll(ids) {
            this.allSelected = !this.allSelected;
            this.selected = this.allSelected ? [...ids] : [];
        },
        toggle(id) {
            const idx = this.selected.indexOf(id);
            if (idx > -1) this.selected.splice(idx, 1);
            else this.selected.push(id);
        },
        isSelected(id) { return this.selected.includes(id); },
        get count() { return this.selected.length; },
        async bulkDelete(url) {
            if (this.selected.length === 0) return;
            const ok = await Alpine.store('confirm').ask(
                `Delete ${this.selected.length} item(s)?`,
                'This action cannot be undone.',
                { confirmText: 'Delete All', variant: 'danger' }
            );
            if (!ok) return;
            try {
                await window.axios.post(url, { ids: this.selected });
                Alpine.store('toast').success(`${this.selected.length} items deleted.`);
                this.selected = [];
                window.location.reload();
            } catch {
                Alpine.store('toast').error('Failed to delete. Please try again.');
            }
        },
    };
};

// Disposition form modal (post-call)
window.dispositionModal = function() {
    return {
        open: false,
        codes: [],
        leadId: null,
        phoneNumber: '',
        selectedCode: '',
        notes: '',
        submitting: false,
        show(leadId, codes, phoneNumber = '') {
            this.leadId = leadId;
            this.codes  = codes;
            this.phoneNumber = phoneNumber;
            this.open   = true;
        },
        async submit() {
            if (!this.selectedCode) {
                Alpine.store('toast').warning('Please select a disposition code.');
                return;
            }
            this.submitting = true;
            const campaign = document.body.dataset.campaign || 'mbsales';
            try {
                await window.axios.post('/api/disposition/save', {
                    campaign_code:    campaign,
                    call_session_id:  Alpine.store('call').sessionId,
                    lead_id:          this.leadId,
                    phone_number:     this.phoneNumber || Alpine.store('call').number,
                    disposition_code: this.selectedCode,
                    notes:            this.notes,
                });
                Alpine.store('toast').success('Disposition saved.');
                Alpine.store('call').state = 'idle';
                Alpine.store('call').setSessionId(null);
                this.open = false;
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to save disposition.');
            } finally {
                this.submitting = false;
            }
        },
    };
};

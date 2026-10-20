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
        hasLoaded: false,
        loading: false,
        error: null,
        stale: false,
        detail: null,
        detailKey: null,
        detailLoading: false,
        detailError: null,
        focusedElement: null,
        pollTimer: null,
        visibilityHandler: null,
        attendanceHandler: null,
        formSubmittedHandler: null,
        listController: null,
        summaryController: null,
        detailController: null,
        listSequence: 0,
        detailSequence: 0,
        _unsubscribeNotifications: null,
        _modalStop: null,
        init() {
            this.visibilityHandler = () => {
                if (document.hidden) {
                    return;
                }
                this.refreshSummary();
                if (this.open) {
                    this.load(true);
                }
            };
            this.attendanceHandler = () => {
                if (this.open) {
                    this.load(true);
                } else {
                    this.refreshSummary();
                }
            };
            this.formSubmittedHandler = () => {
                if (this.open) {
                    this.load(true);
                } else {
                    this.refreshSummary();
                }
            };
            document.addEventListener('visibilitychange', this.visibilityHandler);
            window.addEventListener('attendance-updated', this.attendanceHandler);
            window.addEventListener('form-submitted', this.formSubmittedHandler);
            const configuredPollSeconds = Number(document.body?.dataset.notificationPollSeconds || 60);
            const pollSeconds = Number.isFinite(configuredPollSeconds) ? Math.max(30, configuredPollSeconds) : 60;
            this.pollTimer = window.setInterval(() => {
                if (document.hidden) {
                    return;
                }
                this.refreshSummary();
                if (this.open) {
                    this.load();
                }
            }, pollSeconds * 1000);
            this._modalStop = this.$watch?.('$store.modal.open', (value, previous) => {
                if (previous === 'notification-details' && value !== 'notification-details') {
                    this.restoreFocus();
                }
            });
            this.refreshSummary();
            this.subscribeRealtime();
        },
        toggle() {
            this.open = !this.open;
            if (this.open) {
                this.load(true);
            }
        },
        async refreshSummary() {
            if (this.summaryController) {
                this.summaryController.abort();
            }
            this.summaryController = typeof AbortController === 'function' ? new AbortController() : null;
            try {
                const res = await window.axios.get('/api/notifications/summary', {
                    signal: this.summaryController?.signal,
                });
                this.unread = Math.max(0, Number(res.data.unread ?? 0));
            } catch (error) {
                if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
                    return;
                }
            }
        },
        async load(force = false) {
            if (this.loading && !force) {
                return;
            }
            this.listController?.abort?.();
            const sequence = ++this.listSequence;
            this.listController = typeof AbortController === 'function' ? new AbortController() : null;
            this.loading = true;
            this.error = null;
            try {
                const res = await window.axios.get('/api/notifications', {
                    signal: this.listController?.signal,
                });
                if (sequence !== this.listSequence) {
                    return;
                }
                this.items = Array.isArray(res.data.items) ? res.data.items : [];
                this.unread = Math.max(0, Number(res.data.unread ?? 0));
                this.loaded = true;
                this.hasLoaded = true;
                this.stale = false;
                this.error = null;
            } catch (error) {
                if (sequence !== this.listSequence || error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
                    return;
                }
                this.error = 'Notifications could not be refreshed.';
                this.stale = this.hasLoaded;
            } finally {
                if (sequence === this.listSequence) {
                    this.loading = false;
                }
            }
        },
        async markAllRead() {
            try {
                await window.axios.post('/api/notifications/read-all');
                this.items = this.items.map(n => ({ ...n, read: true }));
                this.unread = 0;
            } catch {
                this.error = 'Notifications could not be marked as read.';
                this.stale = this.hasLoaded;
            }
        },
        async markItemRead(item) {
            const key = item?.key || item?.id;
            if (!key || item.read) {
                return;
            }
            try {
                const response = await window.axios.post('/api/notifications/read', { key });
                this.items = this.items.map(n => (n.key === key || n.id === key ? { ...n, read: true } : n));
                this.unread = Math.max(0, Number(response.data?.unread ?? this.unread - 1));
            } catch (error) {
                if (error?.response?.status !== 404) {
                    this.error = 'This notification could not be marked as read.';
                }
            }
        },
        async openItem(item, event) {
            const key = item?.key || item?.id;
            if (!key || (this.detailLoading && this.detailKey === key)) {
                return;
            }
            this.focusedElement = event?.currentTarget || null;
            this.detailKey = key;
            this.detail = null;
            this.detailError = null;
            this.detailLoading = true;
            this.detailController?.abort?.();
            const detailSequence = ++this.detailSequence;
            this.detailController = typeof AbortController === 'function' ? new AbortController() : null;
            window.Alpine?.store('modal')?.show?.('notification-details');
            this.$nextTick?.(() => {
                const close = document.querySelector('[aria-labelledby="modal-title-notification-details"] button[aria-label="Close dialog"]');
                close?.focus?.();
            });
            try {
                if (!item?.read) {
                    await this.markItemRead(item);
                    if (this.detailKey !== key || detailSequence !== this.detailSequence) {
                        return;
                    }
                }
                const response = await window.axios.get('/api/notifications/detail', {
                    params: { key },
                    signal: this.detailController?.signal,
                });
                if (this.detailKey !== key || detailSequence !== this.detailSequence) {
                    return;
                }
                this.detail = response.data?.detail || null;
                if (!this.detail) {
                    this.detailError = 'This notification is no longer available.';
                }
            } catch (error) {
                if (this.detailKey !== key || detailSequence !== this.detailSequence) {
                    return;
                }
                if (error?.code === 'ERR_CANCELED' || error?.name === 'CanceledError') {
                    return;
                }
                this.detailError = error?.response?.status === 404
                    ? 'This notification is no longer available.'
                    : 'Notification details could not be loaded.';
            } finally {
                if (this.detailKey === key && detailSequence === this.detailSequence) {
                    this.detailLoading = false;
                }
            }
        },
        async retryDetail() {
            const key = this.detailKey;
            if (!key) {
                return;
            }
            const item = this.items.find(n => (n.key || n.id) === key) || { key, id: key, read: true };
            await this.openItem(item);
        },
        restoreFocus() {
            const target = this.focusedElement;
            this.focusedElement = null;
            if (target && document.contains(target)) {
                window.requestAnimationFrame?.(() => target.focus?.());
            }
        },
        subscribeRealtime() {
            const userId = parseInt(document.body?.dataset.userId || '', 10);
            const te = window.TelephonyEcho;
            if (!userId || !te?.initEcho || !te.isBroadcastEnabled()) {
                return;
            }

            te.initEcho();
            this._unsubscribeNotifications = te.subscribeUserNotifications(userId, (notification) => {
                this.receiveRealtime(notification);
            });
        },
        receiveRealtime(notification) {
            const item = this.formatRealtimeNotification(notification);
            const itemKey = item.key || item.id;
            const isNew = !this.items.some(n => (n.key || n.id) === itemKey);
            this.items = [item, ...this.items.filter(n => (n.key || n.id) !== itemKey)].slice(0, 25);
            if (isNew) {
                this.unread = Math.max(0, this.unread) + 1;
            }
            this.loaded = true;
            this.hasLoaded = true;
            this.error = null;
            this.stale = false;

            if (window.Alpine?.store?.('toast')) {
                Alpine.store('toast').info(item.message || item.title || 'New notification', 5000, item.source || 'Notification');
            }

            if (item.show_confetti && typeof window.confetti === 'function') {
                window.confetti({ particleCount: 80, spread: 60, origin: { y: 0.2 } });
            }
            if (this.open) {
                this.load();
            }
        },
        formatRealtimeNotification(notification) {
            const createdAt = notification.sent_at || notification.created_at || new Date().toISOString();
            const rawId = notification.key || notification.id || `${Date.now()}-${Math.random()}`;
            const itemKey = String(rawId).includes(':') ? String(rawId) : `database:${rawId}`;

            return {
                id: itemKey,
                key: itemKey,
                category: notification.category || 'supervisor',
                source: notification.source || 'Notification',
                title: notification.title || 'Update',
                message: notification.message || '',
                time: 'just now',
                created_at: createdAt,
                type: notification.type || 'info',
                read: false,
                recipient_type: notification.recipient_type || null,
                recipient: notification.recipient || null,
                sender_id: notification.sender_id || null,
                sent_at: notification.sent_at || createdAt,
                show_confetti: !!notification.show_confetti,
            };
        },
        destroy() {
            if (this.pollTimer) {
                window.clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.visibilityHandler) {
                document.removeEventListener('visibilitychange', this.visibilityHandler);
            }
            if (this.attendanceHandler) {
                window.removeEventListener('attendance-updated', this.attendanceHandler);
            }
            if (this.formSubmittedHandler) {
                window.removeEventListener('form-submitted', this.formSubmittedHandler);
            }
            this.listController?.abort?.();
            this.summaryController?.abort?.();
            this.detailController?.abort?.();
            this._unsubscribeNotifications?.();
            this._unsubscribeNotifications = null;
            this._modalStop?.();
            if (window.Alpine?.store('modal')?.is?.('notification-details')) {
                window.Alpine.store('modal').hide();
            }
            this.restoreFocus();
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
        dialing: false,
        hangingUp: false,
        show(number, leadId = null) {
            this.phoneNumber = number;
            this.leadId = leadId;
            this.open = true;
        },
        async dial() {
            if (!this.phoneNumber || this.dialing) {
                return;
            }
            const store = Alpine.store('call');
            this.dialing = true;
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
            } finally {
                this.dialing = false;
            }
        },
        async hangup() {
            if (this.hangingUp) {
                return;
            }
            this.hangingUp = true;
            try {
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
            } finally {
                this.hangingUp = false;
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
            const callStore = Alpine.store('call');
            const leadId = this.leadId ?? callStore.leadId ?? null;
            const phoneNumber = this.phoneNumber || callStore.number;
            try {
                await window.axios.post('/api/disposition/save', {
                    campaign_code:    campaign,
                    call_session_id:  callStore.sessionId,
                    lead_id:          leadId,
                    phone_number:     phoneNumber,
                    disposition_code: this.selectedCode,
                    notes:            this.notes,
                });
                Alpine.store('toast').success('Disposition saved.');
                callStore.stopTimer();
                callStore.state = 'idle';
                callStore.setSessionId(null);
                callStore.setLeadId(null);
                window.dispatchEvent(new CustomEvent('disposition-saved', {
                    detail: {
                        campaign,
                        lead_id: leadId,
                        phone_number: phoneNumber,
                        disposition_code: this.selectedCode,
                    },
                }));
                this.leadId = null;
                this.phoneNumber = '';
                this.selectedCode = '';
                this.notes = '';
                this.open = false;
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to save disposition.');
            } finally {
                this.submitting = false;
            }
        },
    };
};

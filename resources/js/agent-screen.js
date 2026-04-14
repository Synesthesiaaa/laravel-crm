window.agentScreen = function() {
    return {
        callState: 'idle',
        sessionId: null,
        phoneNumber: '',
        leadId: '',
        clientName: '',
        duration: 0,
        timer: null,
        muted: false,
        saving: false,
        savingDisposition: false,
        dispositionError: null,
        hasDispositionPending: false,
        dispositionCode: '',
        dispositionNotes: '',
        recentCalls: [],
        dialBlocked: false,
        loadingNextLead: false,
        predictiveMode: false,
        predictiveDelay: 3,
        _predictiveTimer: null,
        transfer: {
            phone_number: '',
            ingroup: '',
        },
        recording: {
            statusText: '',
        },
        dtmf: {
            custom: '',
        },
        callbackForm: {
            datetime: '',
            type: 'ANYONE',
            user: '',
            comments: '',
        },
        leadTools: {
            phone_search: '',
            raw: '',
        },

        _echoUnsubscribe: null,
        _statusPollInterval: null,
        get features() {
            return window.__agentScreenFeatures || {};
        },

        init() {
            this.$watch('callState', (v) => Alpine.store('call').state = v);
            this.$watch('$store.call.duration', (v) => { this.duration = v; });
            this.$watch('$store.call.state', (v) => { if (v) this.callState = v; });
            this.$watch('$store.call.number', (v) => { if (v) this.phoneNumber = v; });
            this.$watch('$store.call.sessionId', (v) => { if (v) this.sessionId = v; });
            if (!this.featureEnabled('predictive_dialing')) {
                this.predictiveMode = false;
            }
            this.syncCallStatus();
            if (this.featureEnabled('session_controls')) {
                this.syncVicidialStatus();
            }

            const te = window.TelephonyEcho;
            const wsAvailable = te && te.initEcho && te.isBroadcastEnabled();
            if (wsAvailable) {
                te.initEcho();
                const userId = parseInt(this.$el.dataset.userId, 10);
                if (userId) {
                    this._echoUnsubscribe = te.subscribeAgentChannel(
                        userId,
                        // onCallStateChanged
                        (p) => this._handleCallStateWs(p),
                        // onVicidialEvent
                        (p) => this._handleVicidialEventWs(p),
                        // onInboundCall (screen pop)
                        (p) => this._handleInboundCallWs(p),
                    );
                }
                // With WS active, use a slow 60s heartbeat fallback instead of 15s polling
                this._statusPollInterval = setInterval(() => this.syncCallStatus(), 60000);
                if (this.featureEnabled('session_controls')) {
                    setInterval(() => this.syncVicidialStatus(), 60000);
                }
            } else {
                // No WebSocket — use 15s polling as before
                this._statusPollInterval = setInterval(() => this.syncCallStatus(), 15000);
                if (this.featureEnabled('session_controls')) {
                    setInterval(() => this.syncVicidialStatus(), 15000);
                }
            }

            window.addEventListener('telephony-shortcut-dial', () => this.dial());
            window.addEventListener('telephony-shortcut-hangup', () => this.hangup());
            window.addEventListener('telephony-shortcut-transfer', () => {
                const panel = document.querySelector('[x-data=\"agentScreen()\"]');
                if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            window.addEventListener('telephony-shortcut-recording', () => {
                if (!this.featureEnabled('recording_controls')) return;
                this.startRecording();
            });
        },

        currentCampaign() {
            return (
                document.body?.dataset?.campaign ||
                Alpine.store('vicidial').campaign ||
                this.$el?.dataset?.campaign ||
                'mbsales'
            );
        },

        /** WebSocket handler: call state changed (from AMI listener or backend) */
        _handleCallStateWs(p) {
            if (String(p.session_id) === String(this.sessionId)) {
                if (['completed','failed','abandoned'].includes(p.to_status)) {
                    clearInterval(this.timer);
                    this.timer = null;
                    Alpine.store('call').stopTimer();
                    this.callState = 'wrapup';
                    Alpine.store('call').state = 'wrapup';
                    if (p.to_status === 'failed') {
                        Alpine.store('toast').warning('Call failed or ended before answer.');
                    } else {
                        Alpine.store('toast').info('Call ended. Please save disposition.');
                    }
                    if (this.predictiveMode && p.to_status === 'failed') {
                        this.schedulePredictiveDial();
                    }
                } else if (p.to_status === 'ringing') {
                    this.callState = 'ringing';
                    Alpine.store('call').state = 'ringing';
                    Alpine.store('toast').info('Call is ringing...');
                } else if (['answered','in_call','on_hold'].includes(p.to_status)) {
                    this.callState = 'connected';
                    Alpine.store('call').state = 'connected';
                    Alpine.store('call').startTimer();
                    Alpine.store('toast').success('Call connected.');
                }
            }
        },

        /** WebSocket handler: ViciDial agent events (state changes, login, etc.) */
        _handleVicidialEventWs(p) {
            const store = Alpine.store('vicidial');
            if (p.event === 'state_ready') {
                store.loggedIn = true;
                store.status = 'ready';
                window.dispatchEvent(new CustomEvent('vicidial-ws-phase', { detail: { phase: 'ready' } }));
                if (window.TelephonyCore?.register) {
                    window.TelephonyCore.register().catch(() => {});
                }
            } else if (p.event === 'state_paused') {
                store.loggedIn = true;
                store.status = 'paused';
            } else if (p.event === 'logged_out' || p.event === 'logged_out_complete') {
                store.loggedIn = false;
                store.status = 'logged_out';
                window.dispatchEvent(new CustomEvent('vicidial-ws-phase', { detail: { phase: 'idle' } }));
                if (window.TelephonyCore?.destroy) {
                    window.TelephonyCore.destroy().catch(() => {});
                }
            } else if (p.event === 'dispo_set') {
                // ViciDial confirmed disposition — no action needed if CRM already saved
            }
            store.lastSyncAt = p.timestamp || new Date().toISOString();
        },

        /** WebSocket handler: inbound/dialer call screen pop */
        _handleInboundCallWs(p) {
            if (p.phone_number) {
                this.phoneNumber = p.phone_number;
                Alpine.store('call').number = p.phone_number;
            }
            if (p.lead_id) {
                this.leadId = String(p.lead_id);
            }
            if (p.client_name) {
                this.clientName = p.client_name;
            }
            Alpine.store('toast').info('Incoming call: ' + (p.phone_number || 'unknown'));
        },

        featureEnabled(key) {
            return !!this.features[key];
        },

        async syncCallStatus() {
            try {
                const res = await window.axios.get('/api/call/status');
                if (res.data.active && res.data.call) {
                    this.sessionId = res.data.call.session_id;
                    this.phoneNumber = res.data.call.phone_number || this.phoneNumber;
                    this.duration = res.data.call.duration_seconds || 0;
                    const statusMap = { dialing: 'dialing', ringing: 'ringing', answered: 'connected', in_call: 'connected', on_hold: 'hold' };
                    this.callState = statusMap[res.data.call.status] || 'connected';
                    Alpine.store('call').state = this.callState;
                    Alpine.store('call').number = this.phoneNumber;
                    Alpine.store('call').setSessionId(this.sessionId);
                    if (this.callState === 'connected') Alpine.store('call').startTimer();
                } else if (res.data.disposition_pending && res.data.pending_call && res.data.pending_call.session_id) {
                    this.dialBlocked = true;
                    this.hasDispositionPending = true;
                    this.callState = 'wrapup';
                    this.sessionId = res.data.pending_call.session_id;
                    this.phoneNumber = res.data.pending_call.phone_number || this.phoneNumber;
                    Alpine.store('call').state = 'wrapup';
                } else {
                    this.dialBlocked = false;
                    this.hasDispositionPending = false;
                    this.callState = 'idle';
                    Alpine.store('call').state = 'idle';
                }
            } catch (e) {
                // On network/auth error, reset to idle so the UI is never
                // permanently stuck in a non-interactive state.
                if (this.callState !== 'connected' && this.callState !== 'dialing' && this.callState !== 'ringing') {
                    this.dialBlocked = false;
                    this.hasDispositionPending = false;
                    this.callState = 'idle';
                    Alpine.store('call').state = 'idle';
                }
            }
        },

        async syncVicidialStatus() {
            try {
                const data = await Alpine.store('vicidial').sync(this.currentCampaign());
                const raw = data?.agent_status?.data?.raw_response || '';

                if (!this.sessionId && typeof raw === 'string' && raw.includes('INCALL')) {
                    this.callState = 'connected';
                    Alpine.store('call').state = 'connected';

                    // ViciDial agent_status pipe format (index 10 = phone_number):
                    // status|call_id|lead_id|campaign|calls_today|full_name|
                    // user_group|user_level|pause_code|rt_sub_status|phone_number|...
                    if (raw.includes('|')) {
                        const lines = raw.split('\n').map(l => l.trim()).filter(Boolean);
                        const dataLine = lines.find(l => l.includes('INCALL') && l.includes('|'));
                        if (dataLine) {
                            const parts = dataLine.split('|');
                            const phone = (parts[10] || '').trim();
                            if (phone && /^\d+$/.test(phone)) {
                                this.phoneNumber = phone;
                                Alpine.store('call').number = phone;
                            }
                        }
                    }
                }
            } catch {}
        },

        async updateIngroups(action) {
            if (!this.featureEnabled('ingroup_management')) return;
            if (window.VicidialSession?.updateIngroups) {
                const ctx = typeof window.getPhoneWidgetCtx === 'function' ? window.getPhoneWidgetCtx() : null;
                await window.VicidialSession.updateIngroups(
                    action,
                    this.parseIngroups(Alpine.store('vicidial').ingroupsRaw || ''),
                    Alpine.store('vicidial').blended,
                    this.currentCampaign(),
                    ctx
                );
            }
        },

        parseIngroups(raw) {
            return (raw || '')
                .split(/[,\s]+/)
                .map(v => v.trim())
                .filter(Boolean);
        },

        async blindTransfer() {
            if (!this.featureEnabled('transfer_controls')) return;
            if (!this.transfer.phone_number) return;
            await this.transferAction('/api/call/transfer/blind', { phone_number: this.transfer.phone_number });
        },

        async warmTransfer() {
            if (!this.featureEnabled('transfer_controls')) return;
            await this.transferAction('/api/call/transfer/warm', {
                phone_number: this.transfer.phone_number || null,
                ingroup: this.transfer.ingroup || null,
                consultative: true,
            });
        },

        async localCloser() {
            if (!this.featureEnabled('transfer_controls')) return;
            if (!this.transfer.ingroup) return;
            await this.transferAction('/api/call/transfer/local', {
                ingroup: this.transfer.ingroup,
                phone_number: this.transfer.phone_number || null,
            });
        },

        async leaveThreeWay() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/leave-3way'); },
        async hangupXfer() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/hangup-xfer'); },
        async hangupBoth() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/hangup-both'); },
        async vmDrop() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/transfer/vm'); },
        async parkCustomer() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/park'); },
        async grabCustomer() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/grab'); },
        async parkIvr() { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/park-ivr'); },
        async swapPark(target) { if (!this.featureEnabled('transfer_controls')) return; await this.transferAction('/api/call/swap-park', { target }); },

        async transferAction(url, data = {}) {
            try {
                await window.axios.post(url, { campaign: this.currentCampaign(), ...data });
                Alpine.store('toast').success('Transfer action sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Transfer action failed.');
            }
        },

        async startRecording() {
            if (!this.featureEnabled('recording_controls')) return;
            try {
                const res = await window.axios.post('/api/call/recording/start', { campaign: this.currentCampaign() });
                this.recording.statusText = res.data?.data?.raw_response || 'Recording started.';
                Alpine.store('toast').success('Recording start sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to start recording.');
            }
        },

        async stopRecording() {
            if (!this.featureEnabled('recording_controls')) return;
            try {
                const res = await window.axios.post('/api/call/recording/stop', { campaign: this.currentCampaign() });
                this.recording.statusText = res.data?.data?.raw_response || 'Recording stopped.';
                Alpine.store('toast').info('Recording stop sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to stop recording.');
            }
        },

        async recordingStatus() {
            if (!this.featureEnabled('recording_controls')) return;
            try {
                const res = await window.axios.get('/api/call/recording/status', { params: { campaign: this.currentCampaign() } });
                this.recording.statusText = res.data?.data?.raw_response || 'No status';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to fetch recording status.');
            }
        },

        async sendDtmf(digit) {
            if (!this.featureEnabled('dtmf_controls')) return;
            const digits = (digit || '').toString().trim();
            if (!digits) return;
            try {
                await window.axios.post('/api/call/dtmf', {
                    campaign: this.currentCampaign(),
                    digits,
                });
                Alpine.store('toast').info('DTMF sent: ' + digits);
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to send DTMF.');
            }
        },

        async scheduleCallback() {
            if (!this.featureEnabled('callback_controls')) return;
            if (!this.leadId || !this.callbackForm.datetime) return;
            try {
                await window.axios.post('/api/callbacks/schedule', {
                    campaign: this.currentCampaign(),
                    lead_id: this.leadId,
                    callback_datetime: this.callbackForm.datetime.replace('T', '+') + ':00',
                    callback_type: this.callbackForm.type,
                    callback_user: this.callbackForm.user || null,
                    callback_comments: this.callbackForm.comments || null,
                });
                Alpine.store('toast').success('Callback scheduled.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to schedule callback.');
            }
        },

        async removeCallback() {
            if (!this.featureEnabled('callback_controls')) return;
            if (!this.leadId) return;
            try {
                await window.axios.post('/api/callbacks/remove', {
                    campaign: this.currentCampaign(),
                    lead_id: this.leadId,
                });
                Alpine.store('toast').info('Callback removed.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to remove callback.');
            }
        },

        async callbackInfo() {
            if (!this.featureEnabled('callback_controls')) return;
            if (!this.leadId) return;
            try {
                const res = await window.axios.get('/api/callbacks/info', {
                    params: {
                        campaign: this.currentCampaign(),
                        lead_id: this.leadId,
                    },
                });
                this.leadTools.raw = res.data?.data?.raw_response || '';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to fetch callback info.');
            }
        },

        async searchLead() {
            if (!this.featureEnabled('lead_tools')) return;
            if (!this.leadTools.phone_search) return;
            try {
                const res = await window.axios.get('/api/leads/search', {
                    params: {
                        campaign: this.currentCampaign(),
                        phone_number: this.leadTools.phone_search,
                    },
                });
                this.leadTools.raw = res.data?.data?.raw_response || '';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Lead search failed.');
            }
        },

        async loadLeadInfo() {
            if (!this.featureEnabled('lead_tools')) return;
            try {
                const params = { campaign: this.currentCampaign() };
                if (this.leadId) params.lead_id = this.leadId;
                if (!this.leadId && this.leadTools.phone_search) params.phone_number = this.leadTools.phone_search;
                const res = await window.axios.get('/api/leads/info', { params });
                this.leadTools.raw = res.data?.data?.raw_response || '';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Unable to fetch lead info.');
            }
        },

        async switchLead() {
            if (!this.featureEnabled('lead_tools')) return;
            if (!this.leadId) return;
            try {
                const res = await window.axios.post('/api/leads/switch', {
                    campaign: this.currentCampaign(),
                    lead_id: this.leadId,
                });
                this.leadTools.raw = res.data?.data?.raw_response || '';
                Alpine.store('toast').success('Lead switch request sent.');
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Lead switch failed.');
            }
        },

        async dial() {
            if (!this.phoneNumber || this.callState !== 'idle' || this.dialBlocked) return;
            if (this.featureEnabled('session_controls') && !Alpine.store('vicidial').loggedIn) {
                Alpine.store('toast').error('Log into VICIdial for this campaign (phone button, bottom-right) before dialing.');
                return;
            }
            this.callState = 'dialing';
            Alpine.store('call').state = 'dialing';
            Alpine.store('call').number = this.phoneNumber;
            try {
                const campaign = this.currentCampaign();
                const res = await window.axios.post('/api/call/dial?campaign=' + encodeURIComponent(campaign), {
                    phone_number: this.phoneNumber,
                    lead_id: this.leadId || null,
                });
                if (res.data.session_id) {
                    this.sessionId = res.data.session_id;
                    Alpine.store('call').setSessionId(res.data.session_id);
                }
                if (!res.data.success) {
                    this.callState = 'idle';
                    Alpine.store('call').state = 'idle';
                    Alpine.store('toast').error(res.data.message || 'Call failed.');
                    return;
                }
                // Wait for SIP.js state + AMI events to transition dialing -> ringing -> connected.
            } catch (e) {
                this.callState = 'idle';
                Alpine.store('call').state = 'idle';
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to originate call. Check connection.');
            }
        },

        async hangup() {
            await Alpine.store('call').hangupWebRTC();
            clearInterval(this.timer);
            this.timer = null;
            Alpine.store('call').stopTimer();
            this.callState = 'wrapup';
            Alpine.store('call').state = 'wrapup';

            // Notify backend so the call session is closed and ViciDial
            // receives external_pause + external_hangup.
            try {
                await window.axios.post('/api/call/hangup', {
                    session_id: this.sessionId || null,
                });
            } catch (e) {
                Alpine.store('toast').warning('Backend hangup request failed — disposition may still be required.');
            }
        },

        async toggleHold() {
            await Alpine.store('call').toggleHoldWebRTC();
            this.callState = Alpine.store('call').state;
        },

        toggleMute() {
            Alpine.store('call').toggleMuteWebRTC();
            this.muted = Alpine.store('call').muted;
        },

        formatDuration(s) {
            const m = String(Math.floor(s / 60)).padStart(2, '0');
            const sec = String(s % 60).padStart(2, '0');
            return `${m}:${sec}`;
        },

        async fetchNextLead() {
            if (this.loadingNextLead || (this.callState !== 'idle' && this.callState !== 'wrapup')) return;
            this.loadingNextLead = true;
            try {
                const campaign = this.currentCampaign();
                const res = await window.axios.get('/api/leads/next', { params: { campaign } });
                if (res.data.lead) {
                    this.leadId = res.data.lead.lead_id || '';
                    this.phoneNumber = res.data.lead.phone_number || '';
                    this.clientName = res.data.lead.client_name || '';
                    Alpine.store('call').number = this.phoneNumber;
                    Alpine.store('toast').success('Lead loaded.');
                } else {
                    Alpine.store('toast').info(res.data.message || 'No leads available.');
                }
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to fetch lead.');
            } finally {
                this.loadingNextLead = false;
            }
        },

        async saveDisposition() {
            if (!this.dispositionCode) return;
            this.savingDisposition = true;
            this.dispositionError = null;
            const campaign = this.currentCampaign();
            try {
                await window.axios.post('/api/disposition/save', {
                    campaign_code:    campaign,
                    call_session_id:  this.sessionId,
                    lead_id:          this.leadId,
                    phone_number:     this.phoneNumber,
                    disposition_code: this.dispositionCode,
                    notes:            this.dispositionNotes,
                    call_duration_seconds: this.duration,
                });
                this.recentCalls.unshift({
                    id:          Date.now(),
                    phone:       this.phoneNumber,
                    time:        new Date().toLocaleTimeString(),
                    disposition: this.dispositionCode,
                });
                if (this.recentCalls.length > 10) this.recentCalls.pop();
                Alpine.store('toast').success('Disposition saved.');
                this.resetAfterDisposition();
            } catch (e) {
                const msg = e.response?.data?.message || 'Failed to save disposition. You may retry or dismiss.';
                this.dispositionError = msg;
                Alpine.store('toast').error(msg);
            } finally {
                this.savingDisposition = false;
            }
        },

        dismissDisposition() {
            Alpine.store('toast').warning('Disposition dismissed. Call has been logged without a code.');
            this.resetAfterDisposition();
        },

        resetAfterDisposition() {
            this.callState = 'idle';
            this.sessionId = null;
            this.dispositionCode = '';
            this.dispositionNotes = '';
            this.dispositionError = null;
            this.hasDispositionPending = false;
            this.duration = 0;
            this.dialBlocked = false;
            Alpine.store('call').state = 'idle';
            if (this.predictiveMode) {
                this.schedulePredictiveDial();
            } else {
                this.fetchNextLead();
            }
        },

        togglePredictiveMode() {
            if (!this.featureEnabled('predictive_dialing')) return;
            this.predictiveMode = !this.predictiveMode;
            if (!this.predictiveMode && this._predictiveTimer) {
                clearTimeout(this._predictiveTimer);
                this._predictiveTimer = null;
            }
            Alpine.store('toast').info(this.predictiveMode ? 'Predictive dialing enabled.' : 'Predictive dialing disabled.');
            if (this.predictiveMode && this.callState === 'idle' && !this.dialBlocked) {
                this.schedulePredictiveDial();
            }
        },

        schedulePredictiveDial() {
            if (!this.featureEnabled('predictive_dialing')) return;
            if (!this.predictiveMode || this.callState !== 'idle') return;
            if (this._predictiveTimer) clearTimeout(this._predictiveTimer);
            this._predictiveTimer = setTimeout(() => this.predictiveDial(), Math.max(1, this.predictiveDelay) * 1000);
        },

        async predictiveDial() {
            if (!this.featureEnabled('predictive_dialing')) return;
            if (!this.predictiveMode || this.callState !== 'idle' || this.dialBlocked) return;
            try {
                const campaign = this.currentCampaign();
                const res = await window.axios.post('/api/call/predictive-dial?campaign=' + encodeURIComponent(campaign));
                if (!res.data.success) {
                    Alpine.store('toast').warning(res.data.message || 'Predictive dial failed.');
                    return;
                }
                if (!res.data.lead) {
                    Alpine.store('toast').info(res.data.message || 'No leads available in hopper.');
                    return;
                }
                this.leadId = res.data.lead.lead_id || '';
                this.phoneNumber = res.data.lead.phone_number || '';
                this.clientName = res.data.lead.client_name || '';
                this.predictiveDelay = Number(res.data.predictive_delay_seconds || this.predictiveDelay || 3);
                this.sessionId = res.data.session_id || null;
                Alpine.store('call').number = this.phoneNumber;
                Alpine.store('call').setSessionId(this.sessionId);
                this.callState = 'dialing';
                Alpine.store('call').state = 'dialing';
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Predictive dial request failed.');
            }
        },

        async saveForm() {
            this.saving = true;
            const form = document.getElementById('capture-form');
            if (!form) { this.saving = false; return; }
            const captureData = {};
            form.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.name && !el.name.startsWith('_')) captureData[el.name] = el.value ?? '';
            });
            try {
                await window.axios.post('/api/agent/capture', {
                    campaign_code: this.currentCampaign(),
                    call_session_id: this.sessionId,
                    lead_id: this.leadId,
                    phone_number: this.phoneNumber,
                    capture_data: captureData,
                });
                Alpine.store('toast').success('Record saved.');
                this.clearForm();
            } catch (e) {
                Alpine.store('toast').error(e.response?.data?.message || 'Failed to save record.');
            } finally {
                this.saving = false;
            }
        },

        clearForm() {
            const form = document.getElementById('capture-form');
            if (form) {
                form.querySelectorAll('input, select, textarea').forEach(el => { el.value = ''; });
            }
        },
    };
};

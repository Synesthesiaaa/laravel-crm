/**
 * Global floating phone / VICIdial session widget (see resources/views/partials/phone-widget.blade.php).
 * `window.__VICIDIAL_SESSION_IFRAME_ONLY` is set inline in the Blade partial before Alpine inits.
 */
window.getPhoneWidgetCtx = function getPhoneWidgetCtx() {
    const el = document.getElementById('phone-widget-root');
    if (!el || !window.Alpine?.$data) {
        return null;
    }
    try {
        return window.Alpine.$data(el);
    } catch (_) {
        return null;
    }
};

window.phoneWidget = function phoneWidget(boot = {}) {
    const panelW = Number(boot.panelW) || 440;
    const panelH = Number(boot.panelH) || 360;

    return {
        open: false,
        panelW,
        panelH,
        sessionControls: boot.sessionControls !== false,

        vici: {
            loading: false,
            phase: 'idle',
            vici_campaign: boot.vici_campaign || 'mbsales',
            agent_campaigns: [],
            agent_campaigns_loading: false,
            agent_campaigns_error: null,
            vd_login: boot.vd_login || '',
            vd_pass: '',
            phone_login: boot.phone_login || '',
            phone_pass: '',
            _verifyPollCount: 0,
            _verifyPollMax: 15,
            last_iframe_url: null,
        },

        parseIngroups(raw) {
            return (raw || '')
                .split(/[,\s]+/)
                .map((v) => v.trim())
                .filter(Boolean);
        },

        currentCampaign() {
            const fromBody = document.body?.dataset?.campaign;
            return this.vici.vici_campaign || fromBody || 'mbsales';
        },

        async persistViciCampaignToSession() {
            const code = this.vici.vici_campaign;
            const row = (this.vici.agent_campaigns || []).find((c) => c.id === code);
            try {
                await window.axios.post('/api/vicidial/session/select-campaign', {
                    campaign: code,
                    campaign_name: row?.name || code,
                });
                if (document.body?.dataset) document.body.dataset.campaign = code;
                Alpine.store('vicidial').campaign = code;
            } catch (_) {}
        },

        async onViciCampaignChange() {
            await this.persistViciCampaignToSession();
        },

        async loadViciAgentCampaigns() {
            if (!this.sessionControls) return;
            this.vici.agent_campaigns_loading = true;
            this.vici.agent_campaigns_error = null;
            try {
                const res = await window.axios.get('/api/vicidial/session/agent-campaigns', {
                    params: { context_campaign: this.currentCampaign() },
                });
                if (res.data?.success && Array.isArray(res.data.campaigns)) {
                    this.vici.agent_campaigns = res.data.campaigns;
                    const ids = this.vici.agent_campaigns.map((c) => c.id);
                    if (ids.length && !ids.includes(this.vici.vici_campaign)) {
                        this.vici.vici_campaign = this.vici.agent_campaigns[0].id;
                        await this.persistViciCampaignToSession();
                    } else if (document.body?.dataset) {
                        document.body.dataset.campaign = this.vici.vici_campaign;
                    }
                }
            } catch (e) {
                this.vici.agent_campaigns_error =
                    e.response?.data?.message || 'Could not load VICIdial campaigns.';
            } finally {
                this.vici.agent_campaigns_loading = false;
            }
        },

        async viciLogin() {
            if (!this.sessionControls || !window.VicidialSession) {
                Alpine.store('toast').error('VICIdial session module is not loaded.');
                return;
            }
            await window.VicidialSession.login({
                campaign: this.currentCampaign(),
                phoneLogin: this.vici.phone_login || null,
                phonePass: this.vici.phone_pass || null,
                vdLogin: this.vici.vd_login || null,
                vdPass: this.vici.vd_pass || null,
                blended: Alpine.store('vicidial').blended,
                ingroups: this.parseIngroups(Alpine.store('vicidial').ingroupsRaw),
                ctx: this,
                maxAttempts: this.vici._verifyPollMax || 15,
            });
        },

        async viciPause(value) {
            if (!this.sessionControls || !window.VicidialSession?.pause) return;
            await window.VicidialSession.pause(value, this.currentCampaign(), this);
        },

        /** Pause when active (ready/in_call); "Active" when paused — matches Vicidial wording */
        async togglePauseActive() {
            if (!this.sessionControls) return;
            if (Alpine.store('vicidial').status === 'paused') {
                await this.viciPause('RESUME');
            } else {
                await this.viciPause('PAUSE');
            }
        },

        async viciLogout() {
            if (!this.sessionControls || !window.VicidialSession?.logout) return;
            await window.VicidialSession.logout(this.currentCampaign(), this);
        },

        async viciPopout() {
            if (!this.sessionControls || !window.VicidialSession?.popout) return;
            await window.VicidialSession.popout(this.currentCampaign(), this);
        },

        _onWsPhase(e) {
            const phase = e.detail?.phase;
            if (phase) this.vici.phase = phase;
        },

        _pauseShortcut() {
            if (!this.sessionControls || !Alpine.store('vicidial').loggedIn) return;
            this.togglePauseActive();
        },

        async init() {
            window.addEventListener('vicidial-ws-phase', this._onWsPhase.bind(this));
            window.addEventListener('telephony-shortcut-pause', this._pauseShortcut.bind(this));

            if (!this.sessionControls) return;

            await this.loadViciAgentCampaigns();

            let viciStatusData = null;
            try {
                viciStatusData = await Alpine.store('vicidial').sync(
                    document.body.dataset.campaign || 'mbsales',
                );
            } catch (_) {}

            try {
                let reconnected = false;
                if (window.VicidialSession?.maybeReconnectPending) {
                    reconnected = await window.VicidialSession.maybeReconnectPending(
                        viciStatusData?.local_session,
                        document.body.dataset.campaign,
                        this,
                    );
                }
                const bootstrap = window.__telephonyBootstrap;
                if (
                    !reconnected &&
                    bootstrap?.campaign &&
                    window.VicidialSession &&
                    !Alpine.store('vicidial').loggedIn
                ) {
                    await window.VicidialSession.login({
                        campaign: bootstrap.campaign,
                        phoneLogin: bootstrap.phone_login || null,
                        phonePass: null,
                        blended: typeof bootstrap.blended === 'boolean' ? bootstrap.blended : true,
                        ingroups: Array.isArray(bootstrap.ingroups) ? bootstrap.ingroups : [],
                        ctx: this,
                    });
                }
            } catch (_) {}

            // Periodically sync session status for phase / INCALL hints on agent page
            const pollMs = 60000;
            setInterval(() => this.syncVicidialStatusFromPhone(), pollMs);
        },

        async syncVicidialStatusFromPhone() {
            if (!this.sessionControls) return;
            try {
                const data = await Alpine.store('vicidial').sync(this.currentCampaign());
                const localStatus = data?.local_session?.session_status || '';
                if (['ready', 'paused', 'in_call'].includes(localStatus)) {
                    if (!['syncing', 'iframe_loading', 'requesting'].includes(this.vici.phase)) {
                        this.vici.phase = 'ready';
                    }
                } else if (localStatus === 'logged_out' && this.vici.phase === 'idle') {
                    this.vici.phase = 'idle';
                }
            } catch (_) {}
        },
    };
};

import {
    beginPointerResize,
    clampLayout,
    createLayoutPersistence,
} from './widgets/layout-manager';

/**
 * Global floating phone / VICIdial session widget (see resources/views/partials/phone-widget.blade.php).
 * `window.__VICIDIAL_SESSION_IFRAME_ONLY` is set inline in the Blade partial before Alpine inits.
 */

/**
 * Match CRM campaign code to a row from VICIdial (exact id, then case-insensitive).
 * @param {Array<{id: string, name?: string}>} agentCampaigns
 * @param {string} crmCode
 * @returns {{id: string, name?: string}|null}
 */
function findCampaignInAgentList(agentCampaigns, crmCode) {
    if (!crmCode || !Array.isArray(agentCampaigns) || agentCampaigns.length === 0) {
        return null;
    }
    const exact = agentCampaigns.find((c) => c && c.id === crmCode);
    if (exact) {
        return exact;
    }
    const lower = String(crmCode).toLowerCase();
    return agentCampaigns.find((c) => c && String(c.id).toLowerCase() === lower) || null;
}

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
    const bounds = {
        minWidth: 340,
        minHeight: 260,
        maxWidthPadding: 16,
        maxHeightPadding: 16,
    };
    let widgetCtx = null;
    const persistence = createLayoutPersistence({
        widgetKey: 'softphone',
        onHydrate: (layout) => widgetCtx?.applyLayout(layout),
    });

    return {
        open: false,
        movable: boot.movable !== false,
        panelW,
        panelH,
        x: Math.max(0, window.innerWidth - panelW - 16),
        y: Math.max(0, window.innerHeight - panelH - 96),
        width: panelW,
        height: panelH,
        isDragging: false,
        isResizing: false,
        suppressToggleClick: false,
        bounds,
        sessionControls: boot.sessionControls !== false,

        vici: {
            loading: false,
            phase: 'idle',
            vici_campaign: boot.vici_campaign || 'mbsales',
            agent_campaigns: [],
            agent_campaigns_loading: false,
            agent_campaigns_error: null,
            campaign_saving: false,
            popout_loading: false,
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

        get widgetStyle() {
            return {
                left: `${this.x}px`,
                top: `${this.y}px`,
            };
        },

        get shellStyle() {
            if (!this.open) {
                return {
                    width: '1px',
                    height: '1px',
                    maxHeight: '1px',
                    maxWidth: '1px',
                    overflow: 'hidden',
                    opacity: 1,
                };
            }

            return {
                width: `${this.width}px`,
                maxWidth: `${this.width}px`,
            };
        },

        get frameWrapStyle() {
            if (!this.open) {
                return {
                    width: '1px',
                    height: '1px',
                    minHeight: '1px',
                    minWidth: '1px',
                    overflow: 'hidden',
                };
            }

            const frameHeight = Math.max(200, this.height - 130);

            return {
                minHeight: '200px',
                height: `${frameHeight}px`,
            };
        },

        applyLayout(layout) {
            const nextSize = clampLayout({
                x: 0,
                y: 0,
                width: Number(layout?.width ?? this.width),
                height: Number(layout?.height ?? this.height),
            }, this.bounds);

            this.width = nextSize.width;
            this.height = nextSize.height;

            const anchor = this.clampAnchorPosition(
                Number(layout?.x ?? this.x),
                Number(layout?.y ?? this.y),
            );
            this.x = anchor.x;
            this.y = anchor.y;

            if (typeof layout?.open === 'boolean') {
                this.open = layout.open;
            }
        },

        currentLayout() {
            return {
                x: Math.round(this.x),
                y: Math.round(this.y),
                width: Math.round(this.width),
                height: Math.round(this.height),
                open: this.open,
            };
        },

        persistLayout() {
            persistence.scheduleSave(() => this.currentLayout());
        },

        clampAnchorPosition(x, y) {
            if (!this.movable) {
                const iconSize = 48;
                return {
                    x: Math.max(0, window.innerWidth - iconSize - 16),
                    y: Math.max(0, window.innerHeight - iconSize - 16),
                };
            }
            const iconSize = 48;
            return {
                x: Math.min(Math.max(Number(x) || 0, 0), Math.max(0, window.innerWidth - iconSize)),
                y: Math.min(Math.max(Number(y) || 0, 0), Math.max(0, window.innerHeight - iconSize)),
            };
        },

        moveAnchorByDelta(startX, startY, deltaX, deltaY) {
            const anchor = this.clampAnchorPosition(startX + deltaX, startY + deltaY);
            this.x = anchor.x;
            this.y = anchor.y;
        },

        beginAnchorDrag(event) {
            if (!this.movable) return;
            if (event.button !== undefined && event.button !== 0) return;
            const originX = event.clientX;
            const originY = event.clientY;
            const startX = this.x;
            const startY = this.y;
            let moved = false;

            this.isDragging = true;

            const onMove = (moveEvent) => {
                const dx = moveEvent.clientX - originX;
                const dy = moveEvent.clientY - originY;
                if (!moved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
                    moved = true;
                }
                this.moveAnchorByDelta(startX, startY, dx, dy);
            };

            const onUp = () => {
                this.isDragging = false;
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', onUp);
                if (moved) {
                    this.suppressToggleClick = true;
                    this.persistLayout();
                    window.setTimeout(() => {
                        this.suppressToggleClick = false;
                    }, 0);
                }
            };

            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp, { once: true });
        },

        toggleOpen(event = null) {
            if (this.suppressToggleClick) {
                if (event?.preventDefault) event.preventDefault();
                return;
            }
            this.open = !this.open;
            this.persistLayout();
        },

        closePanel() {
            this.open = false;
            this.persistLayout();
        },

        onDragStart(event) {
            event.preventDefault();
            this.beginAnchorDrag(event);
        },

        onIconPointerDown(event) {
            this.beginAnchorDrag(event);
        },

        onResizeStart(event) {
            event.preventDefault();
            event.stopPropagation();
            const prevOnEnd = this.onEnd;
            this.onEnd = () => {
                this.persistLayout();
                this.onEnd = prevOnEnd;
            };
            beginPointerResize(event, this);
        },

        onWindowResize() {
            const nextSize = clampLayout({
                x: 0,
                y: 0,
                width: this.width,
                height: this.height,
            }, this.bounds);
            this.width = nextSize.width;
            this.height = nextSize.height;
            const anchor = this.clampAnchorPosition(this.x, this.y);
            this.x = anchor.x;
            this.y = anchor.y;
        },

        /** VICIdial / dialer campaign only — never reads CRM `data-campaign`. */
        telephonyCampaign() {
            const fromBody = document.body?.dataset?.telephonyCampaign;
            return this.vici.vici_campaign || fromBody || 'mbsales';
        },

        async persistViciCampaignToSession() {
            const code = this.vici.vici_campaign;
            const row = (this.vici.agent_campaigns || []).find((c) => c.id === code);
            if (this.vici.campaign_saving) return;
            this.vici.campaign_saving = true;
            try {
                await window.axios.post('/api/vicidial/session/select-campaign', {
                    campaign: code,
                    campaign_name: row?.name || code,
                });
                if (document.body?.dataset) document.body.dataset.telephonyCampaign = code;
                Alpine.store('vicidial').campaign = code;
            } catch (_) {
                Alpine.store('toast').error('Could not save VICIdial campaign.');
            } finally {
                this.vici.campaign_saving = false;
            }
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
                    params: { context_campaign: this.telephonyCampaign() },
                });
                if (res.data?.success && Array.isArray(res.data.campaigns)) {
                    this.vici.agent_campaigns = res.data.campaigns;
                    const crmCode = this.vici.vici_campaign;
                    const match = findCampaignInAgentList(this.vici.agent_campaigns, crmCode);

                    if (match) {
                        // Align softphone state + session vicidial_* to VICIdial canonical id (e.g. casing).
                        if (match.id !== crmCode) {
                            this.vici.vici_campaign = match.id;
                            await this.persistViciCampaignToSession();
                        } else if (document.body?.dataset) {
                            document.body.dataset.telephonyCampaign = match.id;
                        }
                    } else if (this.vici.agent_campaigns.length && crmCode) {
                        // Softphone campaign not in API list: keep selection, prepend for the dropdown.
                        this.vici.agent_campaigns = [
                            { id: crmCode, name: crmCode },
                            ...this.vici.agent_campaigns,
                        ];
                        if (document.body?.dataset) {
                            document.body.dataset.telephonyCampaign = crmCode;
                        }
                    } else if (document.body?.dataset) {
                        document.body.dataset.telephonyCampaign = this.vici.vici_campaign;
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
            if (this.vici.loading || ['requesting','iframe_loading','syncing'].includes(this.vici.phase)) return;
            if (!this.sessionControls || !window.VicidialSession) {
                Alpine.store('toast').error('VICIdial session module is not loaded.');
                return;
            }
            await window.VicidialSession.login({
                campaign: this.telephonyCampaign(),
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
            if (this.vici.loading) return;
            await window.VicidialSession.pause(value, this.telephonyCampaign(), this);
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
            if (this.vici.loading) return;
            await window.VicidialSession.logout(this.telephonyCampaign(), this);
        },

        async viciPopout() {
            if (!this.sessionControls || !window.VicidialSession?.popout) return;
            if (this.vici.popout_loading) return;
            this.vici.popout_loading = true;
            try {
                await window.VicidialSession.popout(this.telephonyCampaign(), this);
            } finally {
                this.vici.popout_loading = false;
            }
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
            widgetCtx = this;
            window.addEventListener('vicidial-ws-phase', this._onWsPhase.bind(this));
            window.addEventListener('telephony-shortcut-pause', this._pauseShortcut.bind(this));
            window.addEventListener('resize', this.onWindowResize.bind(this));
            this.onWindowResize();
            await persistence.load();

            if (!this.sessionControls) return;

            await this.loadViciAgentCampaigns();

            let viciStatusData = null;
            try {
                viciStatusData = await Alpine.store('vicidial').sync(this.telephonyCampaign());
            } catch (_) {}

            try {
                let reconnected = false;
                if (window.VicidialSession?.maybeReconnectPending) {
                    reconnected = await window.VicidialSession.maybeReconnectPending(
                        viciStatusData?.local_session,
                        this.telephonyCampaign(),
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
                const data = await Alpine.store('vicidial').sync(this.telephonyCampaign());
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

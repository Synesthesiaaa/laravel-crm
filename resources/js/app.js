import './bootstrap';
import './echo';
import './components';
import './vicidial-session';
import './phone-widget';
import './soft-navigate';
import TelephonyCore from './telephony-core';

// Make ApexCharts available for dynamic import in views
window.ApexChartsLoader = () => import('apexcharts').then(m => m.default);

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';
import intersect from '@alpinejs/intersect';
import './attendance-status';
import registerLeadImportProgress from './lead-import-progress';

Alpine.plugin(focus);
Alpine.plugin(collapse);
Alpine.plugin(intersect);

registerLeadImportProgress(Alpine);

// Global toast store
Alpine.store('toast', {
    items: [],
    add(type, message, duration = 4000) {
        const id = Date.now() + Math.random();
        this.items.push({ id, type, message });
        if (duration > 0) {
            setTimeout(() => this.remove(id), duration);
        }
        return id;
    },
    remove(id) {
        this.items = this.items.filter(t => t.id !== id);
    },
    success(msg, duration = 4000) { return this.add('success', msg, duration); },
    error(msg, duration = 5000)   { return this.add('error',   msg, duration); },
    warning(msg, duration = 4500) { return this.add('warning', msg, duration); },
    info(msg, duration = 4000)    { return this.add('info',    msg, duration); },
});

// Global modal store
Alpine.store('modal', {
    open: null,
    data: {},
    show(name, data = {}) { this.open = name; this.data = data; },
    hide()                { this.open = null; this.data = {}; },
    is(name)              { return this.open === name; },
});

// Global confirm dialog store
Alpine.store('confirm', {
    visible: false,
    title: '',
    message: '',
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'danger',
    _resolve: null,
    ask(title, message, opts = {}) {
        this.title       = title;
        this.message     = message;
        this.confirmText = opts.confirmText ?? 'Confirm';
        this.cancelText  = opts.cancelText  ?? 'Cancel';
        this.variant     = opts.variant     ?? 'danger';
        this.visible     = true;
        return new Promise(resolve => { this._resolve = resolve; });
    },
    accept()  { this.visible = false; this._resolve?.(true);  this._resolve = null; },
    decline() { this.visible = false; this._resolve?.(false); this._resolve = null; },
});

// Global search store
Alpine.store('search', {
    open: false,
    query: '',
    results: [],
    loading: false,
    recent: JSON.parse(localStorage.getItem('crm_recent_searches') ?? '[]'),
    toggle() { this.open = !this.open; if (!this.open) { this.query = ''; this.results = []; } },
    close()  { this.open = false; this.query = ''; this.results = []; },
    addRecent(q) {
        if (!q.trim()) return;
        this.recent = [q, ...this.recent.filter(r => r !== q)].slice(0, 6);
        localStorage.setItem('crm_recent_searches', JSON.stringify(this.recent));
    },
});

// Global sidebar store
Alpine.store('sidebar', {
    collapsed: localStorage.getItem('sidebar_collapsed') === 'true',
    mobileOpen: false,
    toggle() {
        this.collapsed = !this.collapsed;
        localStorage.setItem('sidebar_collapsed', this.collapsed);
    },
    openMobile()  { this.mobileOpen = true; },
    closeMobile() { this.mobileOpen = false; },
});

// Global call status store (telephony)
Alpine.store('call', {
    state: 'idle', // idle | ringing | connected | hold | wrapup
    sessionId: null,
    number: '',
    duration: 0,
    timer: null,
    muted: false,
    onHold: false,
    transferState: 'idle',
    recording: false,
    inbound: false,

    startTimer() {
        this.duration = 0;
        clearInterval(this.timer);
        this.timer = setInterval(() => this.duration++, 1000);
    },
    stopTimer() { clearInterval(this.timer); this.timer = null; this.duration = 0; },
    setSessionId(id) { this.sessionId = id; },
    formattedDuration() {
        const m = String(Math.floor(this.duration / 60)).padStart(2, '0');
        const s = String(this.duration % 60).padStart(2, '0');
        return `${m}:${s}`;
    },

    // ── WebRTC delegation ──────────────────────────────────────────────────
    async hangupWebRTC() {
        await window.TelephonyCore?.hangup();
    },
    toggleMuteWebRTC() {
        if (!window.TelephonyCore) return;
        this.muted = !this.muted;
        this.muted ? window.TelephonyCore.mute() : window.TelephonyCore.unmute();
    },
    async toggleHoldWebRTC() {
        if (!window.TelephonyCore) return;
        this.onHold = !this.onHold;
        if (this.onHold) {
            await window.TelephonyCore.hold();
        } else {
            await window.TelephonyCore.unhold();
        }
    },
});

// WebSocket connection health store
Alpine.store('ws', {
    state: 'connecting', // connected | connecting | disconnected | unavailable | failed
    get isConnected() { return this.state === 'connected'; },
    get isDisconnected() { return ['disconnected', 'unavailable', 'failed'].includes(this.state); },
    dismissed: false,
    dismiss() { this.dismissed = true; },
});

// Global VICIdial session store
// loggedIn is true ONLY for statuses that represent a fully usable telephony session.
// Transitional values (login_pending, ready_partial) must NOT enable dial/pause actions.
const VICI_USABLE_STATUSES = ['ready', 'paused', 'in_call'];

Alpine.store('vicidial', {
    loggedIn: false,
    status: 'logged_out',
    pauseCode: '',
    queueCount: 0,
    campaign: '',
    blended: true,
    /** Space/comma-separated in-groups string (shared with phone widget + ingroup panel) */
    ingroupsRaw: '',
    ingroups: [],
    lastSyncAt: null,
    async sync(campaign = null) {
        try {
            const params = campaign ? { campaign } : {};
            const { data } = await window.axios.get('/api/vicidial/session/status', { params });
            const session = data.local_session || {};
            const queue = data.queue?.data?.count ?? 0;
            const s = session.session_status || 'logged_out';
            // Only set loggedIn for statuses where the agent is truly functional.
            this.loggedIn = VICI_USABLE_STATUSES.includes(s);
            this.status = s;
            this.pauseCode = session.pause_code || '';
            this.queueCount = Number(queue) || 0;
            this.campaign = session.campaign_code || campaign || this.campaign;
            this.blended = typeof session.blended === 'boolean' ? session.blended : this.blended;
            const choices = (session.ingroup_choices || '').trim();
            this.ingroups = choices ? choices.split(/\s+/) : [];
            if (choices) {
                this.ingroupsRaw = choices;
            }
            this.lastSyncAt = new Date().toISOString();
            return data;
        } catch (_) {
            // silent: UI falls back to local values
            return null;
        }
    },
});

window.Alpine = Alpine;
window.TelephonyCore = TelephonyCore;

/**
 * Graceful logout: hang up active call, unregister SIP.js, close Vicidial session,
 * blank the session iframe, then submit the real logout form.
 *
 * Exposed on window so the header dropdown can call it from `@submit.prevent`
 * without a 15-line inline arrow function.
 */
window.crmGracefulLogout = async function () {
    try {
        const call = Alpine.store('call');
        if (call.state !== 'idle' && call.sessionId) {
            try { await window.axios.post('/api/call/hangup', { session_id: call.sessionId }); } catch (_) {}
        }
        call.state = 'idle';
        call.sessionId = null;
        call.stopTimer();

        if (window.TelephonyCore) {
            try { await window.TelephonyCore.destroy(); } catch (_) {}
        }

        try { await window.axios.post('/api/vicidial/session/logout'); } catch (_) {}

        const frame = document.getElementById('vici-session-frame');
        if (frame) {
            frame.onload = null;
            frame.onerror = null;
            frame.src = 'about:blank';
        }
    } finally {
        const form = document.getElementById('logout-form');
        if (form) {
            HTMLFormElement.prototype.submit.call(form);
        } else {
            window.location.href = '/login';
        }
    }
};

Alpine.start();

// Global keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Cmd+K / Ctrl+K → open global search
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        Alpine.store('search').toggle();
    }
    // Escape → close search
    if (e.key === 'Escape') {
        Alpine.store('search').close();
    }

    // Telephony shortcuts (only active on agent views that listen to these events)
    if ((e.metaKey || e.ctrlKey) && !e.shiftKey && !e.altKey) {
        const key = e.key.toLowerCase();
        const map = {
            d: 'telephony-shortcut-dial',
            h: 'telephony-shortcut-hangup',
            t: 'telephony-shortcut-transfer',
            r: 'telephony-shortcut-recording',
            p: 'telephony-shortcut-pause',
        };
        if (map[key]) {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent(map[key]));
        }
    }
});

// Client-side error logging
window.addEventListener('error', (event) => {
    if (window.axios) {
        window.axios.post('/api/client-errors', {
            message: event.message,
            source:  event.filename,
            line:    event.lineno,
            col:     event.colno,
            url:     location.href,
        }).catch(() => {});
    }
});

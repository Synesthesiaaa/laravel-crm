import './bootstrap';
import './echo';
import './components';

// Make ApexCharts available for dynamic import in views
window.ApexChartsLoader = () => import('apexcharts').then(m => m.default);

import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';
import intersect from '@alpinejs/intersect';

Alpine.plugin(focus);
Alpine.plugin(collapse);
Alpine.plugin(intersect);

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
});

window.Alpine = Alpine;
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

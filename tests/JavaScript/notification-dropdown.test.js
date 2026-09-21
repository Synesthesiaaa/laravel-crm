import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/components.js', import.meta.url), 'utf8');

function setup() {
    const requests = [];
    const listeners = new Map();
    const intervals = new Map();
    let nextInterval = 1;
    let unsubscribeCount = 0;
    const modal = {
        open: null,
        show(name) { this.open = name; },
        hide() { this.open = null; },
        is(name) { return this.open === name; },
    };
    const toast = { info() {} };
    const document = {
        hidden: false,
        body: { dataset: { userId: '7', notificationPollSeconds: '30' } },
        addEventListener(name, handler) { listeners.set(`document:${name}`, handler); },
        removeEventListener(name, handler) {
            if (listeners.get(`document:${name}`) === handler) listeners.delete(`document:${name}`);
        },
        contains() { return true; },
        querySelector() { return { focus() {} }; },
    };
    const axios = {
        get(url, options = {}) {
            return new Promise((resolve, reject) => requests.push({ method: 'get', url, options, resolve, reject }));
        },
        post(url, body) {
            return new Promise((resolve, reject) => requests.push({ method: 'post', url, body, resolve, reject }));
        },
    };
    const echo = {
        initEcho() {},
        isBroadcastEnabled() { return false; },
        subscribeUserNotifications() {
            return () => { unsubscribeCount += 1; };
        },
    };
    const window = {
        axios,
        Alpine: { store(name) { return name === 'modal' ? modal : toast; } },
        TelephonyEcho: echo,
        setInterval(handler) {
            const id = nextInterval++;
            intervals.set(id, handler);
            return id;
        },
        clearInterval(id) { intervals.delete(id); },
        addEventListener(name, handler) { listeners.set(`window:${name}`, handler); },
        removeEventListener(name, handler) {
            if (listeners.get(`window:${name}`) === handler) listeners.delete(`window:${name}`);
        },
        requestAnimationFrame(handler) { handler(); },
    };
    const context = vm.createContext({ window, Alpine: window.Alpine, document, AbortController, console, Date });
    vm.runInContext(source, context);
    const component = window.notificationDropdown();
    component.$watch = () => () => {};
    component.$nextTick = (callback) => callback();

    const respond = (index, data) => requests[index].resolve({ data });
    const fail = (index, error = new Error('offline')) => requests[index].reject(error);
    const emit = (name, event = {}) => listeners.get(`window:${name}`)?.(event);

    return { component, requests, respond, fail, emit, intervals, listeners, modal, document, unsubscribeCount: () => unsubscribeCount };
}

const flush = () => new Promise((resolve) => setTimeout(resolve, 0));

test('refreshes the full list every time the panel opens', async () => {
    const app = setup();
    app.component.init();
    app.respond(0, { unread: 0 });
    await flush();

    app.component.toggle();
    assert.equal(app.requests[1].url, '/api/notifications');
    app.respond(1, { items: [], unread: 0 });
    await flush();
    app.component.toggle();
    app.component.toggle();
    assert.equal(app.requests[2].url, '/api/notifications');
    app.respond(2, { items: [], unread: 0 });
    await flush();
    assert.equal(app.component.hasLoaded, true);
});

test('retains the last successful list and exposes retry state on failure', async () => {
    const app = setup();
    app.component.init();
    app.respond(0, { unread: 0 });
    await flush();
    app.component.toggle();
    const saved = [{ id: 'history:1', key: 'history:1', title: 'Saved', read: false }];
    app.respond(1, { items: saved, unread: 1 });
    await flush();
    app.component.toggle();
    app.component.toggle();
    app.fail(2);
    await flush();
    assert.deepEqual(app.component.items, saved);
    assert.equal(app.component.stale, true);
    assert.equal(app.component.hasLoaded, true);
    assert.equal(app.component.error, 'Notifications could not be refreshed.');
});

test('initial failures expose recovery without showing an empty state', async () => {
    const app = setup();
    app.component.toggle();
    app.fail(0);
    await flush();

    assert.equal(app.component.hasLoaded, false);
    assert.equal(app.component.items.length, 0);
    assert.equal(app.component.error, 'Notifications could not be refreshed.');
});

test('summary polling pauses while the document is hidden', async () => {
    const app = setup();
    app.component.init();
    app.respond(0, { unread: 0 });
    await flush();
    const poll = [...app.intervals.values()][0];

    app.document.hidden = true;
    poll();
    assert.equal(app.requests.length, 1);

    app.document.hidden = false;
    poll();
    assert.equal(app.requests[1].url, '/api/notifications/summary');
});

test('late list responses cannot overwrite the newest request', async () => {
    const app = setup();
    const first = app.component.load(true);
    const second = app.component.load(true);
    app.respond(1, { items: [{ id: 'new', key: 'new' }], unread: 1 });
    await second;
    app.respond(0, { items: [{ id: 'old', key: 'old' }], unread: 1 });
    await first;
    assert.equal(app.component.items[0].id, 'new');
});

test('realtime and REST notifications use one stable source-qualified key', () => {
    const app = setup();
    app.component.items = [{ id: 'database:abc', key: 'database:abc', read: false }];
    app.component.receiveRealtime({ id: 'abc', title: 'Updated' });
    assert.equal(app.component.items.length, 1);
    assert.equal(app.component.items[0].key, 'database:abc');
});

test('rapid detail activation cancels stale detail work and keeps the latest result', async () => {
    const app = setup();
    const first = app.component.openItem({ key: 'history:1', id: 'history:1', read: true });
    const second = app.component.openItem({ key: 'attendance:2', id: 'attendance:2', read: true });
    await flush();
    app.respond(1, { detail: { key: 'attendance:2', title: 'Latest' } });
    await second;
    app.respond(0, { detail: { key: 'history:1', title: 'Stale' } });
    await first;
    assert.equal(app.component.detail.title, 'Latest');
});

test('attendance updates trigger an immediate refresh and destroy removes listeners', async () => {
    const app = setup();
    app.component.init();
    app.respond(0, { unread: 0 });
    await flush();
    app.emit('attendance-updated');
    assert.equal(app.requests[1].url, '/api/notifications/summary');
    app.component.destroy();
    assert.equal(app.listeners.has('window:attendance-updated'), false);
    assert.equal(app.listeners.has('window:form-submitted'), false);
    assert.equal(app.listeners.has('document:visibilitychange'), false);
    assert.equal(app.intervals.size, 0);
});

test('form submissions trigger an immediate notification refresh', async () => {
    const app = setup();
    app.component.init();
    app.respond(0, { unread: 0 });
    await flush();

    app.emit('form-submitted', { detail: { campaign: 'mbsales', formType: 'ezycash' } });
    assert.equal(app.requests[1].url, '/api/notifications/summary');

    app.component.toggle();
    assert.equal(app.requests[2].url, '/api/notifications');
});

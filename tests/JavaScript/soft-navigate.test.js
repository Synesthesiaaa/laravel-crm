import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/soft-navigate.js', import.meta.url), 'utf8');

function setup() {
    const requests = [];
    const scrolls = [];
    const modal = { open: null, hide() { this.open = null; } };
    const main = { innerHTML: 'initial', querySelectorAll: () => [], querySelector: () => null };
    const document = {
        readyState: 'loading', title: '',
        body: { dataset: {}, scrollTop: 640 },
        documentElement: { scrollTop: 640 },
        getElementById: (id) => id === 'main-layout' ? main : null,
        querySelector: () => null, querySelectorAll: () => [], addEventListener() {},
    };
    const window = {
        location: { href: 'http://crm.test/dashboard', pathname: '/dashboard', origin: 'http://crm.test' },
        history: { pushState() {} }, scrollX: 0, scrollY: 640,
        scrollTo: (...position) => scrolls.push(position),
        dispatchEvent() {}, setTimeout, clearTimeout,
        Alpine: { store: () => modal, nextTick: async () => {}, destroyTree() {}, initTree() {} },
    };
    const context = vm.createContext({
        window, document, URL, AbortController, console,
        CustomEvent: class {},
        DOMParser: class {
            parseFromString(html) {
                return {
                    body: { dataset: {} },
                    getElementById: (id) => id === 'main-layout' ? { innerHTML: html } : null,
                    querySelector: () => null,
                };
            }
        },
        fetch: (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject })),
    });
    vm.runInContext(source, context);
    const respond = (index, html) => requests[index].resolve({ ok: true, text: async () => html });
    return { context, window, document, main, requests, scrolls, modal, respond };
}

test('background refresh preserves scroll and releases outgoing modal state', async () => {
    const app = setup();
    app.modal.open = 'sales-summary';
    const pending = app.window.crmSoftNav.refresh();
    app.respond(0, 'updated');
    assert.equal(await pending, true);
    assert.equal(app.main.innerHTML, 'updated');
    assert.deepEqual(app.scrolls, [[0, 640]]);
    assert.equal(app.document.documentElement.scrollTop, 640);
    assert.equal(app.modal.open, null);
});

test('older response cannot overwrite pagination that completes first', async () => {
    const app = setup();
    const old = app.window.crmSoftNav.refresh();
    const latest = vm.runInContext("softNavigate('http://crm.test/records?page=2')", app.context);
    assert.equal(app.requests[0].options.signal.aborted, true);
    app.respond(1, 'page two');
    await latest;
    app.respond(0, 'old dashboard');
    assert.equal(await old, false);
    assert.equal(app.main.innerHTML, 'page two');
});

test('refresh cannot supersede pending foreground navigation', async () => {
    const app = setup();
    const navigation = vm.runInContext("softNavigate('http://crm.test/records?page=2')", app.context);
    assert.equal(await app.window.crmSoftNav.refresh(), false);
    assert.equal(app.requests.length, 1);
    app.respond(0, 'page two');
    await navigation;
});

test('interaction beginning during a request defers its DOM replacement', async () => {
    const app = setup();
    let busy = false;
    const pending = app.window.crmSoftNav.refresh({ shouldDefer: () => busy });
    busy = true;
    app.respond(0, 'updated');
    assert.equal(await pending, false);
    assert.equal(app.main.innerHTML, 'initial');
});

test('failed navigation leaves page usable and permits retry', async () => {
    const app = setup();
    const pending = vm.runInContext("softNavigate('http://crm.test/records?page=2')", app.context);
    app.requests[0].reject(new Error('offline'));
    assert.equal(await pending, null);
    assert.equal(app.main.innerHTML, 'initial');
    const retry = app.window.crmSoftNav.refresh();
    app.respond(1, 'recovered');
    assert.equal(await retry, true);
});

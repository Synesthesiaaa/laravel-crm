import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const source = fs.readFileSync(
    path.join(projectRoot, 'resources', 'js', 'attendance-status.js'),
    'utf8',
);

function loadAttendancePanel({ currentResponses = [] } = {}) {
    const factories = new Map();
    const toasts = [];
    const events = [];
    let reloads = 0;
    let refreshes = 0;
    let alpineInit = null;
    const queuedCurrentResponses = [...currentResponses];

    const window = {
        Alpine: {
            data(name, factory) {
                factories.set(name, factory);
            },
            store(name) {
                if (name !== 'toast') return null;

                return {
                    success(message) { toasts.push(['success', message]); },
                    error(message) { toasts.push(['error', message]); },
                };
            },
        },
        axios: {
            async get(url) {
                assert.equal(url, '/api/attendance/current');
                const response = queuedCurrentResponses.shift() ?? { success: true, open: null, types: [] };
                if (response instanceof Error) {
                    throw response;
                }

                return { data: response };
            },
            async post(url, payload) {
                if (url === '/api/attendance/start') {
                    assert.equal(payload.code, 'lunch');
                    return { data: { success: true, log: { event_type: 'lunch_start' } } };
                }

                assert.equal(url, '/api/attendance/end');
                return { data: { success: true, log: { event_type: 'lunch_end' } } };
            },
        },
        dispatchEvent(event) { events.push(event); },
        location: {
            reload() { reloads += 1; },
        },
        crmSoftNav: {
            async refresh() {
                refreshes += 1;
                return true;
            },
        },
    };

    const context = {
        CustomEvent: class CustomEvent {
            constructor(type, options = {}) {
                this.type = type;
                this.detail = options.detail;
            }
        },
        console,
        document: {
            addEventListener(name, handler) {
                if (name === 'alpine:init') alpineInit = handler;
            },
        },
        window,
    };

    vm.runInNewContext(source, context);
    alpineInit();

    return {
        component: factories.get('attendanceStatusPanel')(),
        get reloads() { return reloads; },
        get refreshes() { return refreshes; },
        toasts,
        events,
    };
}

test('starting an away status updates in place without reloading the telephony page', async () => {
    const harness = loadAttendancePanel({
        currentResponses: [{
            success: true,
            open: { code: 'lunch', label: 'Lunch', started_at: '2026-09-15T06:00:00+08:00' },
            types: [{ code: 'lunch', label: 'Lunch' }],
        }],
    });

    await harness.component.start('lunch');

    assert.equal(harness.reloads, 0);
    assert.equal(harness.component.open.code, 'lunch');
    assert.equal(harness.component.loading, false);
    assert.equal(harness.refreshes, 1);
    assert.equal(harness.events[0].detail.action, 'start');
});

test('ending an away status updates in place without reloading the telephony page', async () => {
    const harness = loadAttendancePanel({
        currentResponses: [{ success: true, open: null, types: [{ code: 'lunch', label: 'Lunch' }] }],
    });
    harness.component.open = { code: 'lunch', label: 'Lunch' };

    await harness.component.end();

    assert.equal(harness.reloads, 0);
    assert.equal(harness.component.open, null);
    assert.equal(harness.component.loading, false);
    assert.equal(harness.refreshes, 1);
    assert.equal(harness.events[0].detail.action, 'end');
});

test('refresh replaces stale status types when the server returns an empty list', async () => {
    const harness = loadAttendancePanel({
        currentResponses: [
            { success: true, open: null, types: [{ code: 'lunch', label: 'Lunch' }] },
            { success: true, open: null, types: [] },
        ],
    });

    await harness.component.refresh();
    assert.equal(harness.component.types.length, 1);

    await harness.component.refresh();
    assert.equal(harness.component.types.length, 0);
});

test('initial load failure becomes a retryable panel error instead of an empty state', async () => {
    const harness = loadAttendancePanel({
        currentResponses: [
            new Error('network down'),
            { success: true, open: null, types: [{ code: 'lunch', label: 'Lunch' }] },
        ],
    });

    await harness.component.init();

    assert.equal(harness.component.ready, true);
    assert.match(harness.component.error, /Could not load attendance statuses/);

    await harness.component.retry();

    assert.equal(harness.component.error, null);
    assert.equal(harness.component.types[0].code, 'lunch');
    assert.equal(harness.component.loading, false);
});

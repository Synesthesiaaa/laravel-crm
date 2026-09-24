import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const source = fs.readFileSync(
    path.join(projectRoot, 'resources', 'js', 'attendance-realtime.js'),
    'utf8',
);

function loadRealtime({ responses = [] } = {}) {
    const factories = new Map();
    const intervals = new Map();
    const queuedResponses = [...responses];
    let intervalId = 0;
    let alpineInit = null;
    let requests = 0;

    const document = {
        hidden: false,
        listeners: new Map(),
        addEventListener(name, handler) {
            if (name === 'alpine:init') {
                alpineInit = handler;
                return;
            }
            this.listeners.set(name, handler);
        },
        removeEventListener(name, handler) {
            if (this.listeners.get(name) === handler) this.listeners.delete(name);
        },
    };
    const window = {
        Alpine: {
            data(name, factory) {
                factories.set(name, factory);
            },
        },
        axios: {
            async get(url) {
                requests += 1;
                assert.equal(url, '/api/attendance/realtime');
                const response = queuedResponses.shift();
                if (response instanceof Error) throw response;
                return { data: response ?? { success: true, sessions: [], stats: {} } };
            },
        },
        setInterval(handler) {
            intervalId += 1;
            intervals.set(intervalId, handler);
            return intervalId;
        },
        clearInterval(id) {
            intervals.delete(id);
        },
    };

    vm.runInNewContext(source, {
        console,
        Date,
        Intl,
        document,
        window,
    });
    alpineInit();

    return {
        component: factories.get('attendanceRealtimeTable')({
            endpoint: '/api/attendance/realtime',
            pollSeconds: 10,
        }),
        document,
        intervals,
        get requests() { return requests; },
    };
}

test('realtime attendance loads current sessions and formats timers', async () => {
    const harness = loadRealtime({
        responses: [{
            success: true,
            sessions: [{
                session_id: 'A-10',
                username: 'agent1',
                login_at: '2026-09-25T01:00:00Z',
                status_since: '2026-09-25T01:30:00Z',
            }],
            stats: { online: 1, available: 1, away: 0, longest_session_seconds: 3600 },
            generated_at: '2026-09-25T02:00:00Z',
        }],
    });

    await harness.component.refresh();

    assert.equal(harness.component.sessions.length, 1);
    assert.equal(harness.component.stats.online, 1);
    assert.equal(harness.component.error, '');
    assert.equal(harness.component.formatDuration(3661), '01:01:01');
    assert.equal(harness.component.formatCompactDuration(3661), '1h 1m');
});

test('polling pauses while document is hidden and destroy clears timers', async () => {
    const harness = loadRealtime({
        responses: [
            { success: true, sessions: [], stats: {} },
            { success: true, sessions: [], stats: {} },
        ],
    });

    harness.component.init();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(harness.requests, 1);
    assert.equal(harness.intervals.size, 2);

    const poll = [...harness.intervals.values()][0];
    harness.document.hidden = true;
    poll();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(harness.requests, 1);

    harness.document.hidden = false;
    poll();
    await new Promise((resolve) => setImmediate(resolve));
    assert.equal(harness.requests, 2);

    harness.component.destroy();
    assert.equal(harness.intervals.size, 0);
    assert.equal(harness.document.listeners.has('visibilitychange'), false);
});

test('failed refresh preserves last successful realtime snapshot', async () => {
    const harness = loadRealtime({
        responses: [
            {
                success: true,
                sessions: [{ session_id: 'A-1', username: 'agent1' }],
                stats: { online: 1 },
            },
            new Error('network down'),
        ],
    });

    await harness.component.refresh();
    await harness.component.refresh();

    assert.equal(harness.component.sessions.length, 1);
    assert.equal(harness.component.stats.online, 1);
    assert.match(harness.component.error, /last successful snapshot/);
});

import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const activityLogView = fs.readFileSync(
    path.join(projectRoot, 'resources', 'views', 'admin', 'activity_log.blade.php'),
    'utf8',
);
const activityLogScript = activityLogView.match(/@push\('scripts'\)\s*<script>([\s\S]*?)<\/script>/)?.[1];

assert.ok(activityLogScript, 'Activity log Alpine component script must be present');

test('reconciles missed activity events while Echo remains connected', async () => {
    let requests = 0;
    const context = {
        window: {
            TelephonyEcho: {
                isBroadcastEnabled: () => true,
                isEchoConnected: () => true,
            },
            axios: {
                get: async () => {
                    requests++;

                    return {
                        data: {
                            data: [{ id: 11, description: 'Latest activity' }],
                        },
                    };
                },
            },
        },
    };

    vm.runInNewContext(activityLogScript, context);

    const component = context.window.activityLogTerminal({
        initialEntries: [{ id: 10, description: 'Previous activity' }],
        historyUrl: '/admin/activity-log/entries',
    });

    await component.poll();

    assert.equal(requests, 1);
    assert.equal(component.lastId, 11);
    assert.equal(component.entries.at(-1).description, 'Latest activity');
});

test('exposes request activities in the terminal filters and details', () => {
    assert.match(activityLogView, /<option value="request">Requests<\/option>/);
    assert.match(activityLogView, /entry\.request/);
});

test('does not duplicate a request activity received by both realtime and polling', async () => {
    const context = {
        window: {
            TelephonyEcho: {
                isBroadcastEnabled: () => true,
                isEchoConnected: () => true,
            },
            axios: {
                get: async () => ({
                    data: {
                        data: [{ id: 11, action: 'GET', description: 'GET /dashboard' }],
                    },
                }),
            },
        },
    };

    vm.runInNewContext(activityLogScript, context);

    const component = context.window.activityLogTerminal({
        initialEntries: [{ id: 10, description: 'Previous activity' }],
        historyUrl: '/admin/activity-log/entries',
    });
    component.$nextTick = () => {};

    component.append({ id: 11, action: 'GET', description: 'GET /dashboard' });
    await component.poll();

    assert.deepEqual(Array.from(component.entries, (entry) => entry.id), [10, 11]);
});

test('renders structured audit detail sections for expanded entries', () => {
    assert.match(activityLogView, /Actor/);
    assert.match(activityLogView, /Event/);
    assert.match(activityLogView, /Request/);
    assert.match(activityLogView, /Before/);
    assert.match(activityLogView, /After/);
    assert.match(activityLogView, /changes\.diff/);
});

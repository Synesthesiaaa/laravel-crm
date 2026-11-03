import assert from 'node:assert/strict';
import test from 'node:test';
import { createUserImportPanel, importErrorMessage } from '../../resources/js/user-import-panel.js';

const run = (id, overrides = {}) => ({ id, phase: 'preview', status: 'processing', active: true, urls: { status: `/runs/${id}`, start: `/runs/${id}/start`, cancel: `/runs/${id}` }, ...overrides });

test('late polling responses cannot replace a newly selected import', async () => {
    let resolveFirst;
    const first = new Promise(resolve => { resolveFirst = resolve; });
    const panel = createUserImportPanel({ runs: [run('first'), run('second')] }, {
        http: { get: url => url.endsWith('first') ? first : Promise.resolve({ data: run('second', { status: 'ready', active: false }) }) },
        schedule: () => 1, cancel: () => {},
    });
    panel.run = panel.runs[0];
    const pending = panel.poll();
    await panel.selectRun(panel.runs[1]);
    resolveFirst({ data: run('first') });
    await pending;
    assert.equal(panel.run.id, 'second');
    assert.equal(panel.run.status, 'ready');
    panel.destroy();
});

test('polling failure retains last progress and destroy prevents rescheduling', async () => {
    let schedules = 0;
    const panel = createUserImportPanel({ runs: [run('one', { processed: 50 })] }, {
        http: { get: async () => { throw new Error('offline'); } },
        schedule: () => ++schedules, cancel: () => {},
    });
    panel.run = panel.runs[0];
    await panel.poll();
    assert.equal(panel.run.processed, 50);
    assert.match(panel.error, /retry/i);
    assert.equal(schedules, 1);
    panel.destroy();
    await panel.poll();
    assert.equal(schedules, 1);
});

test('server failures never display raw exception or credential data', () => {
    const message = importErrorMessage({ response: { status: 500, data: { message: 'SensitiveSqlBinding123!' } } });
    assert.doesNotMatch(message, /SensitiveSqlBinding/);
    assert.match(importErrorMessage({ response: { status: 419 } }), /session/i);
    assert.match(importErrorMessage({ response: { status: 429 } }), /wait/i);
});

test('an upload slower than the normal API timeout can reach the queue response', async () => {
    const OriginalFormData = globalThis.FormData;
    globalThis.FormData = class {
        set() {}
    };
    try {
        const input = { files: [{ size: 1024 }], value: 'staff.csv' };
        const queued = run('uploaded', { status: 'queued' });
        const panel = createUserImportPanel({ runs: [], uploadUrl: '/admin/users/imports' }, {
            http: { post: async (_url, _data, options) => {
                // The shared Axios instance has a 5-second default; this server needs 8 seconds.
                if ((options.timeout ?? 5000) < 8000) {
                    throw Object.assign(new Error('timeout'), { code: 'ETIMEDOUT' });
                }
                return { data: queued };
            } },
            schedule: () => 1, cancel: () => {},
        });
        await panel.upload({ querySelector: () => input });
        assert.equal(panel.error, '');
        assert.equal(panel.run?.status, 'queued');
        assert.equal(input.value, '');
        assert.equal(panel.uploading, false);
        panel.destroy();
    } finally {
        globalThis.FormData = OriginalFormData;
    }
});

test('timeouts and server errors give distinct safe recovery instructions', () => {
    for (const code of ['ETIMEDOUT', 'ECONNABORTED']) {
        assert.match(importErrorMessage({ code }), /timed out/i);
        assert.match(importErrorMessage({ code }), /recent imports/i);
    }
    const serverMessage = importErrorMessage({ response: { status: 500, data: { message: 'SensitiveSqlBinding123!' } } });
    assert.match(serverMessage, /server error.*500/i);
    assert.doesNotMatch(serverMessage, /SensitiveSqlBinding/);
});

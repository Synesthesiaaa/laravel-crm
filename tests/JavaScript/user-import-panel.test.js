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

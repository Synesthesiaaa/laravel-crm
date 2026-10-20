import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

function component(get) {
    const window = { axios: { get } };
    vm.runInNewContext(fs.readFileSync('resources/js/components.js', 'utf8'), { window });
    return window.globalSearch();
}

test('search ignores older responses after a newer query or a clear', async () => {
    const pending = [];
    const search = component(() => new Promise(resolve => pending.push(resolve)));
    search.query = 'first';
    const first = search.search();
    search.query = 'second';
    const second = search.search();
    pending[1]({ data: { groups: [{ label: 'Second' }] } });
    await second;
    pending[0]({ data: { groups: [{ label: 'First' }] } });
    await first;
    assert.equal(search.results[0].label, 'Second');
    search.query = '';
    await search.search();
    assert.equal(search.results.length, 0);
    assert.equal(search.loading, false);
});

test('a failed search exposes an error and retry recovers', async () => {
    let fail = true;
    const search = component(async () => {
        if (fail) throw new Error('Offline');
        return { data: { groups: [] } };
    });
    search.query = 'lead';
    await search.search();
    assert.match(search.error, /could not be completed/);
    assert.equal(search.loading, false);
    fail = false;
    await search.search();
    assert.equal(search.error, null);
});

test('arrow navigation focuses native result links and handles empty results', () => {
    const search = component();
    const focused = [];
    search.$root = { querySelectorAll: () => [] };
    search.focusNext();
    assert.equal(search.focused, -1);
    search.$root.querySelectorAll = () => [0, 1].map(index => ({ focus: () => focused.push(index) }));
    search.focusNext();
    search.focusNext();
    search.focusNext();
    search.focusPrev();
    assert.deepEqual(focused, [0, 1, 0, 1]);
});

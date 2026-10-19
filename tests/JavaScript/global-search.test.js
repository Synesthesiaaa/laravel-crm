import assert from 'node:assert/strict';
import fs from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../resources/js/components.js', import.meta.url), 'utf8');

function setup() {
    const requests = [];
    const window = { axios: { get(url, options) {
        return new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
    } } };
    vm.runInNewContext(source, { window });
    return { component: window.globalSearch(), requests };
}

test('only the latest query may update results or loading state', async () => {
    const { component, requests } = setup();
    component.query = 'Mo';
    const oldSearch = component.search();
    component.query = 'Morgan';
    const newSearch = component.search();
    requests[1].resolve({ data: { groups: [{ label: 'Users', items: [{ title: 'Morgan Reyes' }] }] } });
    await newSearch;
    requests[0].resolve({ data: { groups: [{ label: 'Stale' }] } });
    await oldSearch;
    assert.equal(component.results[0].label, 'Users');
    assert.equal(component.loading, false);
});

test('clearing a query prevents an outstanding response from repopulating results', async () => {
    const { component, requests } = setup();
    component.query = 'Morgan';
    const pending = component.search();
    component.query = '';
    await component.search();
    requests[0].resolve({ data: { groups: [{ label: 'Stale' }] } });
    await pending;
    assert.equal(component.results.length, 0);
    assert.equal(component.loading, false);
});

test('search failures are distinct from no matches and can be retried', async () => {
    const { component, requests } = setup();
    component.query = 'Morgan';
    const pending = component.search();
    requests[0].reject(new Error('Offline'));
    await pending;
    assert.equal(component.error, true);
    const retry = component.search();
    requests[1].resolve({ data: { groups: [] } });
    await retry;
    assert.equal(component.error, false);
});

test('arrow keys move real DOM focus and handle empty results', () => {
    const { component } = setup();
    const focused = [];
    let links = [];
    component.$refs = { searchResults: { querySelectorAll: () => links } };
    component.focusNext();
    assert.equal(component.focused, -1);
    links = [0, 1].map(index => ({ focus: () => focused.push(index) }));
    component.focusPrev();
    component.focusNext();
    assert.deepEqual(focused, [1, 0]);
});

test('opening search focuses its input and watches the debounced model', () => {
    const { component } = setup();
    const watchers = new Map();
    let focuses = 0;
    let searches = 0;
    component.$watch = (name, handler) => watchers.set(name, handler);
    component.$nextTick = handler => handler();
    component.$refs = { searchInput: { focus: () => focuses++ } };
    component.search = () => searches++;
    component.init();
    watchers.get('$store.search.open')(true);
    watchers.get('query')();
    assert.equal(focuses, 1);
    assert.equal(searches, 1);
});

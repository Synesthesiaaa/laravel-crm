import assert from 'node:assert/strict';
import test from 'node:test';
import { readRecentSearches, readSidebarCollapsed, saveRecentSearches, saveSidebarCollapsed } from '../../resources/js/preferences-storage.js';

function localStorageWith(values = {}) {
    const store = new Map(Object.entries(values));

    return {
        getItem(key) { return store.get(key) ?? null; },
        setItem(key, value) { store.set(key, String(value)); },
    };
}

test('preference storage validates search history and tolerates unavailable browser storage', () => {
    globalThis.window = { localStorage: localStorageWith({ crm_recent_searches: '{broken' }) };
    assert.deepEqual(readRecentSearches(), []);
    assert.equal(readSidebarCollapsed(), false);

    window.localStorage.setItem('crm_recent_searches', JSON.stringify([' alpha ', 4, '', 'beta']));
    assert.deepEqual(readRecentSearches(), ['alpha', 'beta']);
    saveRecentSearches(['a', 4, 'b']);
    assert.deepEqual(JSON.parse(window.localStorage.getItem('crm_recent_searches')), ['a', 'b']);

    Object.defineProperty(globalThis, 'window', {
        configurable: true,
        value: Object.defineProperty({}, 'localStorage', { get() { throw new Error('blocked'); } }),
    });
    assert.deepEqual(readRecentSearches(), []);
    assert.equal(readSidebarCollapsed(), false);
    assert.doesNotThrow(() => saveRecentSearches(['safe']));
    assert.doesNotThrow(() => saveSidebarCollapsed(true));
});

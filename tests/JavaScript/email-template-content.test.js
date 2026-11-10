import test from 'node:test';
import assert from 'node:assert/strict';
import { CompositionMode, emailFramePolicy } from '../../resources/js/email-template-content.js';

test('a slow visual load never replaces later raw HTML edits after the operator returns to source', async () => {
    const mode = new CompositionMode();
    let finish;
    let source = '<p>Original</p>';
    const applied = [];
    const loading = mode.select('visual', () => new Promise((resolve) => { finish = resolve; }), (selected, content) => { applied.push(selected); source = content; });
    await mode.select('source', null, (selected) => { applied.push(selected); });
    source = '<p>Latest source edit</p>';
    finish('<p>Old preview response</p>');
    assert.equal(await loading, false);
    assert.equal(source, '<p>Latest source edit</p>');
    assert.equal(mode.mode, 'source');
    assert.deepEqual(applied, ['source']);
});

test('a failed sanitized visual preview keeps the accessible HTML source fallback', async () => {
    const mode = new CompositionMode();
    const applied = [];
    await mode.select('visual', async () => null, (selected) => applied.push(selected));
    assert.equal(mode.mode, 'source');
    assert.deepEqual(applied, ['source']);
});

test('navigation teardown prevents a pending editor load from changing the removed surface', async () => {
    const mode = new CompositionMode();
    let finish;
    let applied = false;
    const loading = mode.select('visual', () => new Promise((resolve) => { finish = resolve; }), () => { applied = true; });
    mode.dispose();
    finish('<p>Old surface</p>');
    assert.equal(await loading, false);
    assert.equal(applied, false);
});

test('email frames allow only configured HTTPS hostname sources and no active content', () => {
    const policy = emailFramePolicy(['Images.Example.com', 'images.example.com', 'http://evil.example', "evil.test; script-src *", '127.0.0.1', 'localhost', '*.example.com']);
    assert.equal(policy, "default-src 'none'; style-src 'unsafe-inline'; img-src https://images.example.com; base-uri 'none'; form-action 'none'");
    assert.equal(emailFramePolicy(null), "default-src 'none'; style-src 'unsafe-inline'; img-src 'none'; base-uri 'none'; form-action 'none'");
});

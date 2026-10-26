import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');
const componentSource = fs.readFileSync(
    path.join(projectRoot, 'resources', 'js', 'agent-capture-webform.js'),
    'utf8',
);
const viewSource = fs.readFileSync(
    path.join(projectRoot, 'resources', 'views', 'agent', 'capture_webform.blade.php'),
    'utf8',
);
const entrySource = fs.readFileSync(
    path.join(projectRoot, 'resources', 'js', 'agent-capture-webform-entry.js'),
    'utf8',
);

test('capture component initializes prefilled values once and can clean up before remount', () => {
    const window = {
        formVisibility() {
            return {
                values: {},
                init(seed) {
                    this.initCount = (this.initCount || 0) + 1;
                    this.lastSeed = seed;
                },
                destroy() {
                    this.cleaned = true;
                },
            };
        },
    };
    const context = { window };
    vm.runInNewContext(componentSource, context);

    const prefill = { customer_name: 'Ada' };
    const component = window.agentCaptureWebform({ campaignCode: 'mbsales', values: prefill });
    component.init();
    component.init();

    assert.equal(component.initCount, 1);
    assert.equal(component.lastSeed, prefill);

    component.destroy();
    assert.equal(component.cleaned, true);
    component.init();
    assert.equal(component.initCount, 2);
});

test('capture Blade relies on Alpine init instead of running the hook twice', () => {
    assert.doesNotMatch(viewSource, /x-init\s*=\s*"init\(/);
});

test('standalone capture entry imports Alpine and starts it after component registration', () => {
    assert.match(entrySource, /import Alpine from ['"]alpinejs['"]/);
    assert.ok(entrySource.indexOf("import './agent-capture-webform'") < entrySource.indexOf('Alpine.start()'));
    assert.match(entrySource, /window\.Alpine\s*=\s*Alpine/);
});

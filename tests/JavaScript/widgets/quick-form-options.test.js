import assert from 'node:assert/strict';
import test from 'node:test';

import {
    hasQuickFormOption,
    normalizeQuickFormOptions,
} from '../../../resources/js/widgets/quick-form-options.js';

test('normalizes active form options and removes invalid duplicates', () => {
    assert.deepEqual(normalizeQuickFormOptions([
        { type: 'ezycash', name: 'EzyCash' },
        { type: 'ezycash', name: 'Duplicate' },
        { type: '', name: 'Invalid' },
        { type: 'ezyconvert' },
    ]), [
        { type: 'ezycash', name: 'EzyCash' },
        { type: 'ezyconvert', name: 'ezyconvert' },
    ]);
});

test('accepts only loaded form types', () => {
    const options = [{ type: 'ezycash', name: 'EzyCash' }];

    assert.equal(hasQuickFormOption(options, 'ezycash'), true);
    assert.equal(hasQuickFormOption(options, 'ezyconvert'), false);
});

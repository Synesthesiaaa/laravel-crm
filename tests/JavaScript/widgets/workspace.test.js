import assert from 'node:assert/strict';
import test from 'node:test';

import {
    isSplitViewport,
    normalizeWorkspaceLayout,
    splitWorkspaceGeometry,
} from '../../../resources/js/widgets/workspace.js';

test('normalizes only an explicit split-screen boolean', () => {
    assert.deepEqual(normalizeWorkspaceLayout({ splitScreen: true }), { splitScreen: true });
    assert.deepEqual(normalizeWorkspaceLayout({ splitScreen: 'yes' }), { splitScreen: false });
    assert.deepEqual(normalizeWorkspaceLayout(null), { splitScreen: false });
});

test('uses split view only at the desktop breakpoint', () => {
    assert.equal(isSplitViewport(1023), false);
    assert.equal(isSplitViewport(1024), true);
});

test('calculates bounded left and right split panels', () => {
    const geometry = splitWorkspaceGeometry(1440, 900);

    assert.equal(geometry.left.left, 16);
    assert.equal(geometry.right.left, geometry.left.left + geometry.left.width + geometry.gap);
    assert.equal(geometry.left.width, geometry.right.width);
    assert.equal(geometry.left.height, 868);
});

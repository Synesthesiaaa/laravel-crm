import assert from 'node:assert/strict';
import test from 'node:test';

import {
    defaultQuickFormPosition,
    FAB_STACK,
    maxShellHeightForFabStack,
} from '../../../resources/js/widgets/layout-manager.js';

test('places a new quick form launcher above the bottom widget stack on short screens', () => {
    globalThis.window = {
        innerWidth: 1366,
        innerHeight: 768,
    };

    const position = defaultQuickFormPosition(520);
    const reservedBottom = FAB_STACK.baseBottomPx
        + (3 * FAB_STACK.sizePx)
        + (2 * FAB_STACK.gapPx);

    assert.equal(position.x, 822);
    assert.equal(position.y, 488);
    assert.equal(position.y, 768 - reservedBottom);
});

test('keeps the softphone panel above the launcher stack', () => {
    globalThis.window = {
        innerWidth: 1366,
        innerHeight: 768,
    };

    assert.equal(maxShellHeightForFabStack({ minHeight: 260, maxHeightPadding: 16 }), 576);
});

test('lets the softphone shrink to the available height on a narrow viewport', () => {
    globalThis.window = {
        innerWidth: 320,
        innerHeight: 568,
    };

    assert.equal(maxShellHeightForFabStack({ minHeight: 388, maxHeightPadding: 16 }), 376);
});

test('uses the layout viewport when a vertical scrollbar reduces available width', () => {
    globalThis.window = {
        innerWidth: 375,
        innerHeight: 667,
    };
    globalThis.document = {
        documentElement: {
            clientWidth: 360,
        },
    };

    assert.equal(defaultQuickFormPosition(296).x, 40);

    delete globalThis.document;
});

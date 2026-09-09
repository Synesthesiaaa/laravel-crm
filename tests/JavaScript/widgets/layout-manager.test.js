import assert from 'node:assert/strict';
import test from 'node:test';

import {
    defaultQuickFormPosition,
    FAB_STACK,
} from '../../../resources/js/widgets/layout-manager.js';

test('places a new quick form launcher above the bottom widget stack on short screens', () => {
    globalThis.window = {
        innerWidth: 1366,
        innerHeight: 768,
    };

    const position = defaultQuickFormPosition(520);
    const reservedBottom = FAB_STACK.baseBottomPx
        + (2 * (FAB_STACK.sizePx + FAB_STACK.gapPx))
        + 16;

    assert.equal(position.x, 822);
    assert.equal(position.y, 768 - reservedBottom);
});

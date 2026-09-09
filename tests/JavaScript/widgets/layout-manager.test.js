import assert from 'node:assert/strict';
import test from 'node:test';

import {
    createLayoutPersistence,
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

test('shares the widget layout request across persistence instances', async () => {
    let requestCount = 0;
    const hydrated = [];

    globalThis.window = {
        innerWidth: 1366,
        innerHeight: 768,
        axios: {
            get: async () => {
                requestCount++;

                return {
                    data: {
                        layouts: {
                            softphone: { x: 10, y: 20 },
                            quick_form: { x: 30, y: 40 },
                        },
                    },
                };
            },
        },
    };

    const softphone = createLayoutPersistence({
        widgetKey: 'softphone',
        onHydrate: (layout) => hydrated.push(['softphone', layout]),
    });
    const quickForm = createLayoutPersistence({
        widgetKey: 'quick_form',
        onHydrate: (layout) => hydrated.push(['quick_form', layout]),
    });

    await Promise.all([softphone.load(), quickForm.load()]);

    assert.equal(requestCount, 1);
    assert.deepEqual(hydrated, [
        ['softphone', { x: 10, y: 20 }],
        ['quick_form', { x: 30, y: 40 }],
    ]);
});

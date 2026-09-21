import { createLayoutPersistence } from './layout-manager.js';

export const SPLIT_VIEW_BREAKPOINT = 1024;

export function normalizeWorkspaceLayout(layout) {
    return {
        splitScreen: layout?.splitScreen === true,
    };
}

export function isSplitViewport(width) {
    return Number(width) >= SPLIT_VIEW_BREAKPOINT;
}

export function splitWorkspaceGeometry(width, height, margin = 16, gap = 16) {
    const viewportWidth = Math.max(0, Number(width) || 0);
    const viewportHeight = Math.max(0, Number(height) || 0);
    const panelWidth = Math.max(320, Math.floor((viewportWidth - (margin * 2) - gap) / 2));

    return {
        gap,
        left: {
            left: margin,
            top: margin,
            width: panelWidth,
            height: Math.max(260, viewportHeight - (margin * 2)),
        },
        right: {
            left: margin + panelWidth + gap,
            top: margin,
            width: panelWidth,
            height: Math.max(260, viewportHeight - (margin * 2)),
        },
    };
}

if (typeof window !== 'undefined') {
    let splitScreen = false;

    const persistence = createLayoutPersistence({
        widgetKey: 'workspace',
        onHydrate: (layout) => setSplitScreen(normalizeWorkspaceLayout(layout).splitScreen, false),
    });

    function emitWorkspaceChange() {
        window.dispatchEvent(new CustomEvent('crm-widget-workspace', {
            detail: { splitScreen },
        }));
    }

    function setSplitScreen(enabled, persist = true) {
        const next = Boolean(enabled);
        if (splitScreen === next) {
            return;
        }

        splitScreen = next;
        emitWorkspaceChange();

        if (persist) {
            persistence.scheduleSave(() => ({ splitScreen }));
        }
    }

    window.crmWidgetWorkspace = {
        isSplitScreen: () => splitScreen,
        setSplitScreen: (enabled) => setSplitScreen(enabled),
        toggle: () => setSplitScreen(!splitScreen),
    };

    document.addEventListener('alpine:initialized', () => {
        void persistence.load();
    }, { once: true });
}

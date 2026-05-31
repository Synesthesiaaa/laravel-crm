const DEFAULT_BOUNDS = {
    minWidth: 260,
    minHeight: 180,
    maxWidthPadding: 24,
    maxHeightPadding: 24,
};

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function getViewportMaxSize(bounds = DEFAULT_BOUNDS) {
    return {
        maxWidth: Math.max(bounds.minWidth, window.innerWidth - (bounds.maxWidthPadding || 0)),
        maxHeight: Math.max(bounds.minHeight, window.innerHeight - (bounds.maxHeightPadding || 0)),
    };
}

export function clampLayout(layout, bounds = DEFAULT_BOUNDS) {
    const { maxWidth, maxHeight } = getViewportMaxSize(bounds);
    const width = clamp(layout.width ?? bounds.minWidth, bounds.minWidth, maxWidth);
    const height = clamp(layout.height ?? bounds.minHeight, bounds.minHeight, maxHeight);

    return {
        ...layout,
        width,
        height,
        x: clamp(layout.x ?? 16, 0, Math.max(0, window.innerWidth - width)),
        y: clamp(layout.y ?? 16, 0, Math.max(0, window.innerHeight - height)),
    };
}

export function createLayoutPersistence({
    widgetKey,
    onHydrate,
    debounceMs = 250,
}) {
    let debounceTimer = null;

    const load = async () => {
        try {
            const { data } = await window.axios.get('/api/widgets/layouts');
            const layout = data?.layouts?.[widgetKey];
            if (layout && typeof layout === 'object') {
                onHydrate(layout);
            }
        } catch (_) {
            // Best effort. Widget should still use defaults.
        }
    };

    const save = async (layout) => {
        try {
            await window.axios.put(`/api/widgets/layouts/${widgetKey}`, { layout });
        } catch (_) {
            // Best effort. No toast to avoid noisy UX while dragging/resizing.
        }
    };

    const scheduleSave = (layoutProvider) => {
        clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(() => {
            save(layoutProvider());
        }, debounceMs);
    };

    return {
        load,
        scheduleSave,
    };
}

export function beginPointerDrag(event, state) {
    const originX = event.clientX;
    const originY = event.clientY;
    const startX = state.x;
    const startY = state.y;

    state.isDragging = true;

    const onMove = (moveEvent) => {
        const next = clampLayout({
            ...state,
            x: startX + (moveEvent.clientX - originX),
            y: startY + (moveEvent.clientY - originY),
        }, state.bounds);
        state.x = next.x;
        state.y = next.y;
    };

    const onUp = () => {
        state.isDragging = false;
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        state.onEnd?.();
    };

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp, { once: true });
}

export function beginPointerResize(event, state) {
    const originX = event.clientX;
    const originY = event.clientY;
    const startW = state.width;
    const startH = state.height;

    state.isResizing = true;

    const onMove = (moveEvent) => {
        const next = clampLayout({
            ...state,
            width: startW + (moveEvent.clientX - originX),
            height: startH + (moveEvent.clientY - originY),
        }, state.bounds);
        state.width = next.width;
        state.height = next.height;
    };

    const onUp = () => {
        state.isResizing = false;
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        state.onEnd?.();
    };

    window.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp, { once: true });
}

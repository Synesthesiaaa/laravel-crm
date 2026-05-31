import {
    beginPointerResize,
    clampLayout,
    createLayoutPersistence,
} from './widgets/layout-manager';

window.quickFormWidget = function quickFormWidget(boot = {}) {
    const defaultWidth = 520;
    const defaultHeight = 640;
    const bounds = {
        minWidth: 360,
        minHeight: 360,
        maxWidthPadding: 16,
        maxHeightPadding: 16,
    };
    let widgetCtx = null;
    const persistence = createLayoutPersistence({
        widgetKey: 'quick_form',
        onHydrate: (layout) => widgetCtx?.applyLayout(layout),
    });

    return {
        open: false,
        x: Math.max(0, window.innerWidth - defaultWidth - 84),
        y: Math.max(0, window.innerHeight - defaultHeight - 32),
        width: defaultWidth,
        height: defaultHeight,
        isDragging: false,
        isResizing: false,
        suppressToggleClick: false,
        bounds,
        loading: false,
        error: null,
        frameSrc: boot.current_form_url || null,

        get widgetStyle() {
            return {
                left: `${this.x}px`,
                top: `${this.y}px`,
            };
        },

        get shellStyle() {
            if (!this.open) {
                return {
                    width: '1px',
                    height: '1px',
                    maxHeight: '1px',
                    maxWidth: '1px',
                    overflow: 'hidden',
                    opacity: 1,
                };
            }

            return {
                width: `${this.width}px`,
                height: `${this.height}px`,
            };
        },

        applyLayout(layout) {
            const nextSize = clampLayout({
                x: 0,
                y: 0,
                width: Number(layout?.width ?? this.width),
                height: Number(layout?.height ?? this.height),
            }, this.bounds);
            this.width = nextSize.width;
            this.height = nextSize.height;

            const anchor = this.clampAnchorPosition(
                Number(layout?.x ?? this.x),
                Number(layout?.y ?? this.y),
            );
            this.x = anchor.x;
            this.y = anchor.y;

            if (typeof layout?.open === 'boolean') {
                this.open = layout.open;
            }
        },

        currentLayout() {
            return {
                x: Math.round(this.x),
                y: Math.round(this.y),
                width: Math.round(this.width),
                height: Math.round(this.height),
                open: this.open,
            };
        },

        persistLayout() {
            persistence.scheduleSave(() => this.currentLayout());
        },

        clampAnchorPosition(x, y) {
            const iconSize = 48;
            return {
                x: Math.min(Math.max(Number(x) || 0, 0), Math.max(0, window.innerWidth - iconSize)),
                y: Math.min(Math.max(Number(y) || 0, 0), Math.max(0, window.innerHeight - iconSize)),
            };
        },

        moveAnchorByDelta(startX, startY, deltaX, deltaY) {
            const anchor = this.clampAnchorPosition(startX + deltaX, startY + deltaY);
            this.x = anchor.x;
            this.y = anchor.y;
        },

        beginAnchorDrag(event) {
            if (event.button !== undefined && event.button !== 0) return;
            const originX = event.clientX;
            const originY = event.clientY;
            const startX = this.x;
            const startY = this.y;
            let moved = false;

            this.isDragging = true;

            const onMove = (moveEvent) => {
                const dx = moveEvent.clientX - originX;
                const dy = moveEvent.clientY - originY;
                if (!moved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
                    moved = true;
                }
                this.moveAnchorByDelta(startX, startY, dx, dy);
            };

            const onUp = () => {
                this.isDragging = false;
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', onUp);
                if (moved) {
                    this.suppressToggleClick = true;
                    this.persistLayout();
                    window.setTimeout(() => {
                        this.suppressToggleClick = false;
                    }, 0);
                }
            };

            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp, { once: true });
        },

        toggleOpen(event = null) {
            if (this.suppressToggleClick) {
                if (event?.preventDefault) event.preventDefault();
                return;
            }
            this.open = !this.open;
            this.persistLayout();
        },

        closePanel() {
            this.open = false;
            this.persistLayout();
        },

        onDragStart(event) {
            event.preventDefault();
            this.beginAnchorDrag(event);
        },

        onIconPointerDown(event) {
            this.beginAnchorDrag(event);
        },

        onResizeStart(event) {
            event.preventDefault();
            event.stopPropagation();
            const prevOnEnd = this.onEnd;
            this.onEnd = () => {
                this.persistLayout();
                this.onEnd = prevOnEnd;
            };
            beginPointerResize(event, this);
        },

        onWindowResize() {
            const nextSize = clampLayout({
                x: 0,
                y: 0,
                width: this.width,
                height: this.height,
            }, this.bounds);
            this.width = nextSize.width;
            this.height = nextSize.height;
            const anchor = this.clampAnchorPosition(this.x, this.y);
            this.x = anchor.x;
            this.y = anchor.y;
        },

        async resolveDefaultSource() {
            if (boot.current_form_url) {
                this.frameSrc = boot.current_form_url;
                this.error = null;
                return;
            }

            this.loading = true;
            this.error = null;
            try {
                const { data } = await window.axios.get('/api/forms/quick/bootstrap');
                if (!data?.success || !data?.form_url) {
                    this.error = data?.message || 'Unable to resolve quick form source.';
                    return;
                }
                this.frameSrc = data.form_url;
            } catch (error) {
                this.error = error?.response?.data?.message || 'Unable to load quick form.';
            } finally {
                this.loading = false;
            }
        },

        async init() {
            widgetCtx = this;
            window.addEventListener('resize', this.onWindowResize.bind(this));
            this.onWindowResize();
            await persistence.load();
            await this.resolveDefaultSource();
        },
    };
};

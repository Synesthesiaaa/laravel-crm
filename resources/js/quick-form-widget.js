import {
    beginPointerDrag,
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
            const next = clampLayout({
                x: Number(layout?.x ?? this.x),
                y: Number(layout?.y ?? this.y),
                width: Number(layout?.width ?? this.width),
                height: Number(layout?.height ?? this.height),
            }, this.bounds);
            this.x = next.x;
            this.y = next.y;
            this.width = next.width;
            this.height = next.height;

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

        toggleOpen() {
            this.open = !this.open;
            this.persistLayout();
        },

        closePanel() {
            this.open = false;
            this.persistLayout();
        },

        onDragStart(event) {
            event.preventDefault();
            const prevOnEnd = this.onEnd;
            this.onEnd = () => {
                this.persistLayout();
                this.onEnd = prevOnEnd;
            };
            beginPointerDrag(event, this);
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
            const next = clampLayout({
                x: this.x,
                y: this.y,
                width: this.width,
                height: this.height,
            }, this.bounds);
            this.x = next.x;
            this.y = next.y;
            this.width = next.width;
            this.height = next.height;
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
            await persistence.load();
            await this.resolveDefaultSource();
        },
    };
};

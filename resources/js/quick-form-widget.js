import {
    createLayoutPersistence,
    defaultQuickFormPosition,
} from './widgets/layout-manager';

const FORM_ROUTE_PATTERN = /\/forms\/([^/?#]+)/i;
const ICON_GAP_PX = 8;

function clamp(value, min, max) {
    if (value < min) return min;
    if (value > max) return max;
    return value;
}

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

    const defaultPosition = defaultQuickFormPosition(defaultWidth, defaultHeight);

    return {
        open: false,
        x: defaultPosition.x,
        y: defaultPosition.y,
        width: defaultWidth,
        height: defaultHeight,
        isDragging: false,
        isResizing: false,
        suppressToggleClick: false,
        bounds,
        loading: false,
        error: null,
        frameSrc: boot.current_form_url || null,
        frameKey: 0,
        refreshOnOpen: false,
        currentCampaign: boot.current_campaign || null,
        currentFormType: boot.current_form_type || null,

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
            const anchor = this.clampAnchorPosition(
                Number(layout?.x ?? this.x),
                Number(layout?.y ?? this.y),
            );
            this.x = anchor.x;
            this.y = anchor.y;

            const size = this.clampSizeForCurrentAnchor(
                Number(layout?.width ?? this.width),
                Number(layout?.height ?? this.height),
            );
            this.width = size.width;
            this.height = size.height;

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

        maxWidthForCurrentAnchor() {
            // Panel opens upper-left from icon, so width is limited by left-side space.
            const iconGap = 8; // 0.5rem
            const minLeftMargin = Math.max(8, this.bounds.maxWidthPadding || 16);
            const available = this.x - iconGap - minLeftMargin;
            return Math.max(this.bounds.minWidth, available);
        },

        maxHeightForCurrentAnchor() {
            // Panel opens upper-left from icon, so height is limited by top-side space.
            const iconGap = 8; // 0.5rem
            const minTopMargin = Math.max(8, this.bounds.maxHeightPadding || 16);
            const available = this.y - iconGap - minTopMargin;
            return Math.max(this.bounds.minHeight, available);
        },

        clampSizeForAnchorAt(anchorX, anchorY, width, height) {
            const iconGap = 8;
            const minLeftMargin = Math.max(8, this.bounds.maxWidthPadding || 16);
            const minTopMargin = Math.max(8, this.bounds.maxHeightPadding || 16);
            const maxWidth = Math.max(this.bounds.minWidth, anchorX - iconGap - minLeftMargin);
            const maxHeight = Math.max(this.bounds.minHeight, anchorY - iconGap - minTopMargin);

            return {
                width: Math.min(Math.max(width, this.bounds.minWidth), maxWidth),
                height: Math.min(Math.max(height, this.bounds.minHeight), maxHeight),
            };
        },

        clampSizeForCurrentAnchor(width, height) {
            return this.clampSizeForAnchorAt(this.x, this.y, width, height);
        },

        clampAnchorPosition(x, y) {
            const iconSize = 48;
            return {
                x: Math.min(Math.max(Number(x) || 0, 0), Math.max(0, window.innerWidth - iconSize)),
                y: Math.min(Math.max(Number(y) || 0, 0), Math.max(0, window.innerHeight - iconSize)),
            };
        },

        buildEmbedFormUrl(formType, campaign, frameKey = 0) {
            const params = new URLSearchParams({
                campaign: String(campaign || ''),
                widget_embed: '1',
                _wfs: String(frameKey || 0),
            });
            return `/forms/${encodeURIComponent(String(formType || ''))}?${params.toString()}`;
        },

        syncFrameSrc(formType, campaign, options = {}) {
            if (!formType || !campaign) return false;
            const force = Boolean(options.force);
            const semanticChanged = this.currentFormType !== formType || this.currentCampaign !== campaign;
            if (!semanticChanged && !force) return false;
            this.currentFormType = formType;
            this.currentCampaign = campaign;
            this.frameKey += 1;
            const nextSrc = this.buildEmbedFormUrl(formType, campaign, this.frameKey);
            this.frameSrc = nextSrc;
            this.refreshOnOpen = !this.open;
            this.error = null;
            return true;
        },

        syncFromUrl(rawUrl) {
            let url;
            try {
                url = new URL(rawUrl || window.location.href, window.location.origin);
            } catch (_) {
                return false;
            }

            const match = url.pathname.match(FORM_ROUTE_PATTERN);
            if (!match?.[1]) {
                return false;
            }

            const formType = decodeURIComponent(match[1]);
            const campaign = url.searchParams.get('campaign') || document.body?.dataset?.campaign || 'mbsales';
            return this.syncFrameSrc(formType, campaign);
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
            if (this.open && this.refreshOnOpen && this.currentFormType && this.currentCampaign) {
                this.syncFrameSrc(this.currentFormType, this.currentCampaign, { force: true });
                this.refreshOnOpen = false;
            }
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

        onResizeStart(event, corner = 'se') {
            event.preventDefault();
            event.stopPropagation();
            event.target?.setPointerCapture?.(event.pointerId);
            const originX = event.clientX;
            const originY = event.clientY;
            const startX = this.x;
            const startY = this.y;
            const startWidth = this.width;
            const startHeight = this.height;
            const minWidth = this.bounds.minWidth;
            const minHeight = this.bounds.minHeight;

            const leftLimit = Math.max(8, this.bounds.maxWidthPadding || 16);
            const topLimit = Math.max(8, this.bounds.maxHeightPadding || 16);

            const anchorMinX = 0;
            const anchorMaxX = Math.max(0, window.innerWidth - 48);
            const anchorMinY = 0;
            const anchorMaxY = Math.max(0, window.innerHeight - 48);
            const rightMin = anchorMinX - ICON_GAP_PX;
            const rightMax = anchorMaxX - ICON_GAP_PX;
            const bottomMin = anchorMinY - ICON_GAP_PX;
            const bottomMax = anchorMaxY - ICON_GAP_PX;

            const startRight = startX - ICON_GAP_PX;
            const startBottom = startY - ICON_GAP_PX;
            const startLeft = startRight - startWidth;
            const startTop = startBottom - startHeight;

            this.isResizing = true;

            const onMove = (moveEvent) => {
                const dx = moveEvent.clientX - originX;
                const dy = moveEvent.clientY - originY;

                let left = startLeft;
                let right = startRight;
                let top = startTop;
                let bottom = startBottom;

                if (corner === 'nw') {
                    left = clamp(startLeft + dx, leftLimit, right - minWidth);
                    top = clamp(startTop + dy, topLimit, bottom - minHeight);
                } else if (corner === 'ne') {
                    right = clamp(startRight + dx, startLeft + minWidth, rightMax);
                    top = clamp(startTop + dy, topLimit, bottom - minHeight);
                } else if (corner === 'sw') {
                    left = clamp(startLeft + dx, leftLimit, right - minWidth);
                    bottom = clamp(startBottom + dy, startTop + minHeight, bottomMax);
                } else {
                    // se
                    right = clamp(startRight + dx, startLeft + minWidth, rightMax);
                    bottom = clamp(startBottom + dy, startTop + minHeight, bottomMax);
                }

                right = Math.max(right, rightMin);
                bottom = Math.max(bottom, bottomMin);

                this.width = Math.max(minWidth, right - left);
                this.height = Math.max(minHeight, bottom - top);
                this.x = right + ICON_GAP_PX;
                this.y = bottom + ICON_GAP_PX;
            };

            const onUp = () => {
                this.isResizing = false;
                event.target?.releasePointerCapture?.(event.pointerId);
                window.removeEventListener('pointermove', onMove);
                window.removeEventListener('pointerup', onUp);
                this.persistLayout();
            };

            window.addEventListener('pointermove', onMove);
            window.addEventListener('pointerup', onUp, { once: true });
        },

        onWindowResize() {
            const anchor = this.clampAnchorPosition(this.x, this.y);
            this.x = anchor.x;
            this.y = anchor.y;
            const size = this.clampSizeForCurrentAnchor(this.width, this.height);
            this.width = size.width;
            this.height = size.height;
        },

        async resolveDefaultSource() {
            if (boot.current_form_url) {
                const synced = this.syncFrameSrc(
                    boot.current_form_type || this.currentFormType,
                    boot.current_campaign || this.currentCampaign || 'mbsales',
                    { force: true },
                );
                if (!synced) {
                    this.frameKey += 1;
                    this.frameSrc = `${boot.current_form_url}${boot.current_form_url.includes('?') ? '&' : '?'}_wfs=${this.frameKey}`;
                }
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
                const synced = this.syncFrameSrc(data.form_type, data.campaign, { force: true });
                if (!synced) {
                    this.frameKey += 1;
                    this.frameSrc = `${data.form_url}${data.form_url.includes('?') ? '&' : '?'}_wfs=${this.frameKey}`;
                }
            } catch (error) {
                this.error = error?.response?.data?.message || 'Unable to load quick form.';
            } finally {
                this.loading = false;
            }
        },

        async init() {
            widgetCtx = this;
            window.addEventListener('resize', this.onWindowResize.bind(this));
            window.addEventListener('soft-navigate', (event) => {
                const switched = this.syncFromUrl(event?.detail?.url || window.location.href);
                if (!switched && !this.frameSrc) {
                    this.resolveDefaultSource();
                }
            });
            document.addEventListener('click', (event) => {
                const anchor = event.target?.closest?.('a[href]');
                if (!anchor) return;
                if (!FORM_ROUTE_PATTERN.test(anchor.pathname || '')) return;
                this.syncFromUrl(anchor.href);
            }, true);
            this.onWindowResize();
            await persistence.load();
            const switched = this.syncFromUrl(window.location.href);
            if (!switched) {
                await this.resolveDefaultSource();
            }
        },
    };
};

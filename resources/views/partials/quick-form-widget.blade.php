@php
    $isFormsPage = request()->routeIs('forms.show');
    $currentFormType = $isFormsPage ? (string) request()->route('type') : null;
    $currentCampaign = (string) request()->query('campaign', session('campaign', 'mbsales'));
    $quickFormBoot = [
        'current_form_type' => $currentFormType,
        'current_campaign' => $currentCampaign,
        'current_form_url' => $isFormsPage && $currentFormType
            ? route('forms.show', ['type' => $currentFormType, 'campaign' => $currentCampaign, 'widget_embed' => 1])
            : null,
    ];
@endphp

<div id="quick-form-widget-root"
     class="fixed z-20"
     :class="isSplitActive() ? 'widget-root--split widget-root--split-right' : ''"
     x-data="quickFormWidget(@js($quickFormBoot))"
     :style="widgetStyle"
     x-init="init()"
     @click.stop>
    <button type="button"
            class="widget-launcher absolute left-0 top-0 z-20 flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[var(--color-surface-elevated)] text-[var(--color-on-surface)] shadow-lg transition hover:bg-[var(--color-surface-2)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] cursor-move"
            @pointerdown="onIconPointerDown($event)"
            @click="toggleOpen($event)"
            :aria-expanded="open"
            aria-controls="quick-form-widget-shell"
            title="Quick form widget">
        <x-icon name="document-text" class="w-6 h-6" />
    </button>

    <div id="quick-form-widget-shell"
         class="widget-panel-upper-left absolute origin-bottom-right flex flex-col overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg"
         :class="isResizing ? 'transition-none' : 'transition-all duration-300 ease-out'"
         :style="shellStyle">
        <div x-show="open"
             x-transition.opacity.duration.200ms
             class="flex items-center justify-between gap-2 bg-[var(--color-surface-elevated)] px-3 py-2 shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <button type="button"
                        class="btn-ghost text-[10px] px-2 py-1 shrink-0 cursor-move"
                        @pointerdown="onDragStart($event)"
                        title="Drag widget">
                    <x-icon name="bars-3" class="w-3.5 h-3.5" />
                </button>
                <span class="text-xs font-semibold text-[var(--color-on-surface)] truncate">Quick Form</span>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <button type="button"
                        class="btn-ghost text-[10px] px-2 py-1"
                        @click="toggleSplitScreen()"
                        :title="isSplitActive() ? 'Exit split view' : 'Open split view'">
                    <span x-text="isSplitActive() ? 'Exit split' : 'Split view'"></span>
                </button>
                <button type="button"
                        class="btn-ghost text-[10px] px-2 py-1"
                        @click="closePanel()"
                        title="Minimize widget">
                    <x-icon name="chevron-down" class="w-4 h-4" />
                </button>
            </div>
        </div>

        <div x-show="open" class="flex-1 min-h-0 relative">
            <template x-if="error">
                <div class="p-4 text-xs text-red-600" x-text="error"></div>
            </template>
            <template x-if="loading">
                <div class="p-4 text-xs text-[var(--color-on-surface-dim)]">Loading form…</div>
            </template>
            <iframe x-show="!loading && !error"
                    :src="frameSrc || 'about:blank'"
                    class="block w-full h-full border-0 bg-transparent"
                    title="Quick campaign form"
                    style="min-height: 280px;"></iframe>
        </div>

        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--nw"
                @pointerdown="onResizeStart($event, 'nw')"
                aria-label="Resize quick form widget from top-left"></button>
        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--ne"
                @pointerdown="onResizeStart($event, 'ne')"
                aria-label="Resize quick form widget from top-right"></button>
        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--sw"
                @pointerdown="onResizeStart($event, 'sw')"
                aria-label="Resize quick form widget from bottom-left"></button>
        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--se"
                @pointerdown="onResizeStart($event, 'se')"
                aria-label="Resize quick form widget from bottom-right"></button>
    </div>
</div>

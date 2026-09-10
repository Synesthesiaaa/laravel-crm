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
     class="fixed z-40"
     :class="isSplitActive() ? 'widget-root--split widget-root--split-right' : ''"
     x-data="quickFormWidget(@js($quickFormBoot))"
     :style="widgetStyle"
     x-init="init()"
     @click.stop>
    <button type="button"
            class="widget-launcher absolute left-0 top-0 z-20 flex h-12 w-12 shrink-0 cursor-move items-center justify-center rounded-full bg-[var(--color-surface-elevated)] text-[var(--color-on-surface)] transition hover:bg-[var(--color-surface-2)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
            @pointerdown="onIconPointerDown($event)"
            @click="toggleOpen($event)"
            :aria-expanded="open"
            :aria-label="open ? 'Minimize Quick Form widget' : 'Open Quick Form widget'"
            aria-controls="quick-form-widget-shell"
            title="Quick form widget; drag to move">
        <x-icon name="document-text" class="w-6 h-6" />
    </button>

    <div id="quick-form-widget-shell"
         class="widget-shell widget-panel-upper-left absolute flex flex-col overflow-hidden"
         :class="{
             'widget-shell--open': open || isSplitActive(),
             'transition-none': isResizing,
         }"
         :style="shellStyle">
        <div x-show="open"
             x-transition.opacity.duration.200ms
             class="widget-shell-header">
            <div class="widget-header-leading">
                <button type="button"
                        class="widget-header-button widget-header-button--drag shrink-0"
                        @pointerdown="onDragStart($event)"
                        aria-label="Drag Quick Form widget"
                        title="Drag widget">
                    <x-icon name="bars-3" class="w-3.5 h-3.5" />
                </button>
                <div class="widget-header-title-group">
                    <span x-show="formOptions.length === 0"
                          class="widget-header-title">Quick Form</span>
                    <label for="quick-form-selector" class="sr-only">Quick form selection</label>
                    <select id="quick-form-selector"
                            x-show="formOptions.length > 0"
                            aria-label="Quick form selection"
                            class="form-select widget-form-select"
                            :value="currentFormType || ''"
                            @change="selectForm($event.target.value)"
                            :disabled="formsLoading || formOptions.length === 0">
                        <template x-for="option in formOptions" :key="option.type">
                            <option :value="option.type" x-text="option.name"></option>
                        </template>
                    </select>
                    <span class="widget-header-context">
                        Campaign: <strong x-text="currentCampaign || '—'"></strong>
                    </span>
                </div>
            </div>
            <div class="widget-header-actions">
                <button type="button"
                        class="widget-header-button widget-header-action"
                        @click="toggleSplitScreen()"
                        :aria-label="isSplitActive() ? 'Exit split view' : 'Open split view'"
                        :title="isSplitActive() ? 'Exit split view' : 'Open split view'">
                    <x-icon name="squares-plus" class="h-4 w-4" />
                    <span class="widget-action-label" x-text="isSplitActive() ? 'Exit split' : 'Split view'"></span>
                </button>
                <button type="button"
                        class="widget-header-button"
                        @click="closePanel()"
                        aria-label="Minimize Quick Form widget"
                        title="Minimize widget">
                    <x-icon name="chevron-down" class="w-4 h-4" />
                </button>
            </div>
        </div>
        <div x-show="open" class="widget-shell-content" :aria-busy="loading">
            <template x-if="error">
                <div class="widget-state widget-state--error" role="alert" aria-live="assertive">
                    <x-icon name="exclamation-triangle" class="h-6 w-6" />
                    <strong class="widget-state-title">Form unavailable</strong>
                    <p class="widget-state-message" x-text="error"></p>
                    <button type="button"
                            class="btn-ghost widget-state-action"
                            @click="retryLoad()">
                        Retry
                    </button>
                </div>
            </template>
            <template x-if="loading">
                <div class="widget-state widget-state--loading" role="status" aria-live="polite">
                    <x-icon name="arrow-path" class="h-5 w-5 animate-spin text-[var(--color-primary)]" aria-hidden="true" />
                    <span>Loading form…</span>
                </div>
            </template>
            <iframe x-show="!loading && !error"
                    :src="frameSrc || 'about:blank'"
                    class="block h-full min-h-0 w-full border-0 bg-transparent"
                    title="Quick campaign form"></iframe>
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

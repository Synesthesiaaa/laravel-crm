<script>
    window.__VICIDIAL_SESSION_IFRAME_ONLY = @json((bool) config('vicidial.session_iframe_agent_api_only', false));
</script>
@php
    $defaultVicidialCampaign = (string) (
        session('vicidial_campaign')
        ?? auth()->user()?->default_campaign
        ?? 'mbsales'
    );
    $phoneWidgetBoot = [
        'vici_campaign' => $defaultVicidialCampaign,
        'phone_login' => (string) (auth()->user()->extension ?? ''),
        'vd_login' => (string) (auth()->user()->vici_user ?? ''),
        'panelW' => (int) config('vicidial.session_iframe_panel_width_px', 440),
        'panelH' => (int) config('vicidial.session_iframe_panel_height_px', 360),
        'sessionControls' => true,
    ];
@endphp
{{-- VICIdial session: FAB + expandable panel. Iframe is never inside x-show (WebRTC). Collapsed = 1×1px viewport slot. --}}
<div id="phone-widget-root"
     class="fab-stack-1 flex flex-col-reverse items-end gap-2"
     :class="isSplitActive() ? 'widget-root--split widget-root--split-left' : ''"
     :style="rootStyle"
     x-data="phoneWidget(@js($phoneWidgetBoot))"
     x-init="init()"
     @click.stop>

    <button type="button"
            class="widget-launcher flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[var(--color-surface-elevated)] text-[var(--color-on-surface)] shadow-lg transition hover:bg-[var(--color-surface-2)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] relative"
            @click="toggleOpen()"
            :aria-expanded="open"
            aria-controls="phone-widget-shell"
            title="Phone / VICIdial session">
        <x-icon name="phone" class="w-6 h-6" />
        <span class="absolute -top-0.5 -right-0.5 flex h-3 w-3 rounded-full border-2 border-[var(--color-surface)]"
              :class="{
                  'bg-emerald-500': vici.phase === 'ready' && $store.vicidial.loggedIn,
                  'bg-amber-400': ['requesting','iframe_loading','syncing'].includes(vici.phase),
                  'bg-red-500': ['failed','timeout'].includes(vici.phase),
                  'bg-[var(--color-on-surface-dim)]': vici.phase === 'idle' && !$store.vicidial.loggedIn,
              }"></span>
        <span x-show="$store.vicidial.queueCount > 0"
              class="absolute -bottom-1 -left-1 min-w-[1.1rem] rounded-full bg-[var(--color-primary)] px-1 text-[10px] font-bold leading-tight text-white text-center"
              x-text="$store.vicidial.queueCount > 99 ? '99+' : $store.vicidial.queueCount"
              style="display: none;"></span>
    </button>

    <div id="phone-widget-shell"
         class="phone-widget-shell relative flex flex-col overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg"
         :class="{
             'phone-widget-shell--open': open,
             'transition-none': isResizing || isSplitterResizing,
             'transition-all duration-300 ease-out': !isResizing && !isSplitterResizing,
         }"
         :style="shellStyle">

        <div x-show="open"
             x-transition.opacity.duration.200ms
             class="flex items-center justify-between gap-2 bg-[var(--color-surface-elevated)] px-3 py-2 shrink-0 border-b border-[var(--color-border)]">
            <div class="flex items-center gap-2 min-w-0">
                <span class="text-xs font-semibold text-[var(--color-on-surface)] truncate">Phone</span>
                <span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full shrink-0"
                      :class="{
                          'status-chip-ready':  vici.phase === 'ready',
                          'status-chip-warn':   ['requesting','iframe_loading','syncing'].includes(vici.phase),
                          'status-chip-error':  ['failed','timeout'].includes(vici.phase),
                          'status-chip-idle':   vici.phase === 'idle',
                      }"
                      x-text="{
                          idle:          'Offline',
                          requesting:    'Starting…',
                          iframe_loading:'Opening…',
                          syncing:       'Confirming…',
                          ready:         'Online',
                          failed:        'Failed',
                          timeout:       'Timed out',
                      }[vici.phase] || vici.phase">
                </span>
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
                        title="Minimize (session keeps running)">
                    <x-icon name="chevron-down" class="w-4 h-4" />
                </button>
            </div>
        </div>

        {{-- Login controls: fixed height via splitter; hidden when minimized (not wrapping iframe) --}}
        <div x-show="open"
             x-transition.opacity.duration.200ms
             id="phone-widget-panel"
             class="flex flex-col shrink-0 overflow-hidden border-b border-[var(--color-border)] min-h-0"
             :style="controlsPanelStyle">
            <div class="overflow-y-auto flex-1 min-h-0 px-3 py-3 space-y-3 text-[var(--color-on-surface)]">
                <p class="text-[11px] text-[var(--color-on-surface-dim)] leading-snug">
                    Minimize to a corner chip — the dialer stays loaded for WebRTC.
                </p>

                @if(config('vicidial.session_iframe_agent_api_only') && (! config('vicidial.session_iframe_confirm_non_agent_live') || config('vicidial.session_iframe_skip_non_agent_live_check')))
                    <p class="text-[11px] text-amber-900 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 leading-snug">
                        @if(config('vicidial.session_iframe_skip_non_agent_live_check'))
                            Non-Agent live check is skipped (<span class="font-mono text-[10px]">VICI_SESSION_SKIP_NON_AGENT_LIVE_CHECK</span>).
                        @else
                            Iframe-only without Non-Agent confirmation — enable <span class="font-mono text-[10px]">VICI_SESSION_IFRAME_CONFIRM_NON_AGENT_LIVE</span> or turn off iframe-only mode.
                        @endif
                    </p>
                @elseif(config('vicidial.session_iframe_agent_api_only'))
                    <p class="text-[11px] text-[var(--color-on-surface-dim)] leading-snug">
                        Non-Agent verify: VD_login must match <span class="font-mono">vici_user</span>.
                    </p>
                @endif

                <div class="grid grid-cols-2 gap-2">
                    <div class="form-field col-span-2">
                        <label class="form-label">VD Login <span class="text-[var(--color-danger)]">*</span></label>
                        <input class="form-input" x-model="vici.vd_login" placeholder="VICIdial user login"
                               autocomplete="off" autocapitalize="none" spellcheck="false"
                               :disabled="$store.vicidial.loggedIn || ['requesting','iframe_loading','syncing'].includes(vici.phase)" />
                    </div>
                    <div class="form-field col-span-2">
                        <label class="form-label">VD Pass</label>
                        <input type="password" class="form-input" x-model="vici.vd_pass" placeholder="VICIdial password"
                               autocomplete="new-password" autocapitalize="none" spellcheck="false"
                               data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                               :disabled="$store.vicidial.loggedIn || ['requesting','iframe_loading','syncing'].includes(vici.phase)" />
                    </div>
                    <div class="form-field col-span-2">
                        <label class="form-label">Phone Login <span class="text-[var(--color-danger)]">*</span></label>
                        <input class="form-input" x-model="vici.phone_login" placeholder="Extension e.g. 6001"
                               autocomplete="off" autocapitalize="none" spellcheck="false"
                               :disabled="$store.vicidial.loggedIn || ['requesting','iframe_loading','syncing'].includes(vici.phase)" />
                    </div>
                    <div class="form-field col-span-2">
                        <label class="form-label">Phone Pass</label>
                        <input type="password" class="form-input" x-model="vici.phone_pass" placeholder="SIP password"
                               autocomplete="new-password" autocapitalize="none" spellcheck="false"
                               data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                               :disabled="$store.vicidial.loggedIn || ['requesting','iframe_loading','syncing'].includes(vici.phase)" />
                    </div>
                </div>

                <div x-show="['requesting','iframe_loading','syncing'].includes(vici.phase)"
                     class="flex items-center gap-1.5 text-[11px] text-amber-600">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    <span x-text="{
                        requesting:    'Sending login request…',
                        iframe_loading:'Loading VICIdial…',
                        syncing:       'Confirming agent (' + vici._verifyPollCount + '/' + vici._verifyPollMax + ')…',
                    }[vici.phase] || ''"></span>
                </div>

                <div x-show="vici.phase === 'failed' || vici.phase === 'timeout'" class="text-[11px] text-red-600">
                    Login did not complete. Check Phone Login and retry.
                </div>

                <div class="flex flex-wrap gap-2">
                    <template x-if="!$store.vicidial.loggedIn">
                        <button class="btn-primary text-xs" @click="viciLogin()"
                                :disabled="['requesting','iframe_loading','syncing'].includes(vici.phase) || !vici.phone_login || !vici.vd_login">
                            <x-icon name="power" class="w-3.5 h-3.5" />
                            <span x-text="['requesting','iframe_loading','syncing'].includes(vici.phase) ? 'Connecting…' : 'Login'"></span>
                        </button>
                    </template>
                    <template x-if="$store.vicidial.loggedIn">
                        <button type="button" class="btn-secondary text-xs" @click="togglePauseActive()"
                                :disabled="vici.loading || ['requesting','iframe_loading','syncing'].includes(vici.phase)">
                            <span x-text="$store.vicidial.status === 'paused' ? 'Active' : 'Pause'"></span>
                        </button>
                    </template>
                    <template x-if="$store.vicidial.loggedIn">
                        <button type="button" class="btn-ghost text-xs" @click="viciLogout()"
                                :disabled="vici.loading">Logout</button>
                    </template>
                </div>

                <div x-show="$store.vicidial.loggedIn && (vici.phase === 'ready' || vici.phase === 'syncing' || vici.phase === 'iframe_loading')" class="space-y-1">
                    <button type="button"
                            class="btn-ghost text-xs w-full flex items-center gap-1.5 justify-center border border-[var(--color-border)] rounded-lg py-1.5"
                            @click="viciPopout()">
                        <x-icon name="arrow-top-right-on-square" class="w-3.5 h-3.5" />
                        Open VICIdial in new window
                    </button>
                </div>
            </div>
        </div>

        <div x-show="open"
             role="separator"
             aria-orientation="horizontal"
             aria-label="Resize dialer panel"
             class="widget-splitter shrink-0"
             @pointerdown="onSplitterResizeStart($event)"></div>

        {{-- Iframe: always in DOM; flex fill when open (never display:none) --}}
        <div id="vici-session-frame-wrap"
             class="relative min-h-0 overflow-hidden bg-black/5"
             :class="open ? 'flex-1' : 'shrink-0'">
            <iframe id="vici-session-frame"
                    src="about:blank"
                    class="block border-0 bg-transparent"
                    :class="open ? 'h-full w-full' : 'h-px w-px'"
                    style="min-width: 1px; min-height: 1px;"
                    allow="microphone *; autoplay *"
                    title="VICIdial agent session"></iframe>
        </div>
        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--nw"
                @pointerdown="onResizeStart($event, 'nw')"
                aria-label="Resize softphone widget from top-left"></button>
        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--ne"
                @pointerdown="onResizeStart($event, 'ne')"
                aria-label="Resize softphone widget from top-right"></button>
        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--sw"
                @pointerdown="onResizeStart($event, 'sw')"
                aria-label="Resize softphone widget from bottom-left"></button>
        <button x-show="open"
                type="button"
                class="widget-resize-handle widget-resize-handle--se"
                @pointerdown="onResizeStart($event, 'se')"
                aria-label="Resize softphone widget from bottom-right"></button>
    </div>
</div>

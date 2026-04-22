<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(session()->has('lead_import_track'))
        <script>
            window.__LEAD_IMPORT_TRACK__ = @json(session('lead_import_track'));
        </script>
    @endif
    <script>
      (function() {
        var t = localStorage.getItem('theme') || 'dark';
        document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <title>@yield('title', config('app.name'))</title>
    {{-- Self-hosted DM Sans font (fallback to system) --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="min-h-screen flex" style="margin: 0;" x-data x-cloak
      data-campaign="{{ session('campaign', 'mbsales') }}"
      data-telephony-campaign="{{ session('vicidial_campaign') ?? session('campaign', 'mbsales') }}">

    {{-- Mobile sidebar overlay --}}
    <div x-show="$store.sidebar.mobileOpen"
         x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-black/60 z-30 lg:hidden"
         @click="$store.sidebar.closeMobile()"
         style="display: none;">
    </div>

    @include('layouts.sidebar')

    {{-- Main layout --}}
    <div id="main-layout"
         class="flex-1 flex flex-col min-h-screen transition-all duration-[280ms] ease-[cubic-bezier(0.4,0,0.2,1)]"
         :class="{
             'lg:ml-[280px]': !$store.sidebar.collapsed,
             'lg:ml-[72px]':   $store.sidebar.collapsed,
             'ml-0': true
         }">

        {{-- Sticky header --}}
        <header class="md-header" role="banner">
            <div class="flex items-center gap-3 min-w-0">
                {{-- Mobile hamburger --}}
                <button type="button"
                        class="lg:hidden btn-icon mr-1"
                        @click="$store.sidebar.openMobile()"
                        aria-label="Open navigation">
                    <x-icon name="bars-3" class="w-5 h-5" />
                </button>
                {{-- Desktop sidebar toggle --}}
                <button type="button"
                        class="hidden lg:inline-flex btn-icon"
                        @click="$store.sidebar.toggle()"
                        aria-label="Toggle sidebar">
                    <x-icon name="bars-3" class="w-5 h-5" />
                </button>
                <h1 class="text-base font-semibold tracking-tight flex items-center gap-2 text-[var(--color-on-surface)] truncate">
                    @yield('header-icon', '')
                    <span class="truncate">@yield('header-title', 'Dashboard')</span>
                </h1>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @yield('header-actions')

                {{-- Global search trigger --}}
                <button type="button"
                        class="btn-icon hidden sm:inline-flex"
                        @click="$store.search.toggle()"
                        title="Search (Ctrl+K)"
                        aria-label="Open global search">
                    <x-icon name="magnifying-glass" class="w-4 h-4" />
                </button>

                {{-- Notifications bell --}}
                <div class="relative" x-data="notificationDropdown()">
                    <button type="button"
                            class="btn-icon relative"
                            @click="toggle()"
                            aria-label="Notifications">
                        <x-icon name="bell" class="w-4 h-4" />
                        <span x-show="unread > 0"
                              x-text="unread > 9 ? '9+' : unread"
                              class="notification-badge">
                        </span>
                    </button>
                    {{-- Notifications dropdown --}}
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                         @click.outside="open = false"
                         class="notifications-dropdown"
                         style="display: none;">
                        <div class="flex items-center justify-between p-4 border-b border-[var(--color-border)]">
                            <span class="font-semibold text-sm text-[var(--color-on-surface)]">Notifications</span>
                            <button x-show="unread > 0" @click="markAllRead()" class="text-xs text-[var(--color-primary)] hover:underline">
                                Mark all read
                            </button>
                        </div>
                        <div class="max-h-80 overflow-y-auto">
                            <template x-if="items.length === 0">
                                <div class="p-6 text-center text-sm text-[var(--color-on-surface-dim)]">
                                    <x-icon name="bell-slash" class="w-8 h-8 mx-auto mb-2 opacity-40" />
                                    No notifications
                                </div>
                            </template>
                            <template x-for="n in items" :key="n.id">
                                <div class="notif-item" :class="{ 'notif-unread': !n.read }">
                                    <div class="flex items-start gap-3">
                                        <div class="notif-dot" :class="n.type === 'error' ? 'bg-red-500' : n.type === 'warning' ? 'bg-amber-500' : 'bg-[var(--color-primary)]'"></div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-[var(--color-on-surface)] leading-snug" x-text="n.message"></p>
                                            <p class="text-xs text-[var(--color-on-surface-dim)] mt-0.5" x-text="n.time"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Call status indicator (telephony) --}}
                <div x-show="$store.call.state !== 'idle'"
                     class="call-status-badge"
                     :class="{
                         'call-connected': $store.call.state === 'connected',
                         'call-hold':      $store.call.state === 'hold',
                         'call-wrapup':    $store.call.state === 'wrapup',
                         'call-ringing':   $store.call.state === 'ringing',
                     }"
                     style="display: none;">
                    <x-icon name="phone" class="w-3.5 h-3.5" />
                    <span x-show="$store.call.state === 'ringing'">Ringing...</span>
                    <span x-show="$store.call.state === 'connected'" x-text="'On Call · ' + $store.call.formattedDuration()"></span>
                    <span x-show="$store.call.state === 'hold'">On Hold</span>
                    <span x-show="$store.call.state === 'wrapup'">Wrap-up</span>
                </div>

                <div x-show="$store.vicidial.loggedIn"
                     class="call-status-badge border border-[var(--color-border)] bg-[var(--color-surface-2)] text-[var(--color-on-surface)]"
                     style="display: none;">
                    <x-icon name="signal" class="w-3.5 h-3.5" />
                    <span x-text="'Vici: ' + ($store.vicidial.status || 'ready')"></span>
                    <span x-show="$store.vicidial.pauseCode">· <span x-text="$store.vicidial.pauseCode"></span></span>
                    <span>· Q:<span x-text="$store.vicidial.queueCount"></span></span>
                </div>

                {{-- Theme toggle --}}
                <button type="button" id="theme-toggle" class="btn-icon theme-toggle" aria-label="Toggle theme">
                    <x-icon name="moon" class="theme-icon-dark w-4 h-4" />
                    <x-icon name="sun" class="theme-icon-light w-4 h-4 hidden" />
                </button>

                {{-- User dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button type="button"
                            class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-[var(--color-surface-3)] transition-colors text-sm"
                            @click="open = !open">
                        <div class="w-7 h-7 rounded-full bg-[var(--color-primary-muted)] border border-[var(--color-primary)] flex items-center justify-center text-[var(--color-primary)] font-bold text-xs uppercase">
                            {{ substr($user->full_name ?? $user->username ?? 'U', 0, 1) }}
                        </div>
                        <span class="hidden sm:block text-[var(--color-on-surface-muted)] max-w-[120px] truncate">
                            {{ $user->full_name ?? $user->username }}
                        </span>
                        <x-icon name="chevron-down" class="w-3.5 h-3.5 text-[var(--color-on-surface-dim)]" />
                    </button>
                    <div x-show="open"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                         @click.outside="open = false"
                         class="dropdown-panel right-0 w-48"
                         style="display: none;">
                        <div class="px-3 py-2 border-b border-[var(--color-border)]">
                            <p class="text-xs font-semibold text-[var(--color-on-surface)] truncate">{{ $user->full_name ?? $user->username }}</p>
                            <p class="text-xs text-[var(--color-on-surface-dim)] truncate">{{ $user->role }}</p>
                        </div>
                        <a href="{{ route('attendance.index') }}" class="dropdown-item">
                            <x-icon name="clock" class="w-4 h-4" />
                            My Attendance
                        </a>
                        <div class="border-t border-[var(--color-border)] mt-1 pt-1">
                            <form method="POST" action="{{ route('logout') }}" id="logout-form" @submit.prevent="window.crmGracefulLogout && window.crmGracefulLogout()">
                                @csrf
                                <button type="submit" class="dropdown-item w-full text-red-400 hover:text-red-300">
                                    <x-icon name="arrow-right-on-rectangle" class="w-4 h-4" />
                                    Sign out
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="content-padding flex-1 p-6 lg:p-8" id="main-content">
            @yield('content')
        </main>
    </div>

    {{-- ============================================================
         GLOBAL UI OVERLAYS (toast, modal, search, confirm, telephony)
         ============================================================ --}}

    {{-- Toast notification container --}}
    <div class="toast-container" aria-live="polite" aria-atomic="false">
        <template x-for="toast in $store.toast.items" :key="toast.id">
            <div class="toast-item"
                 :class="{
                     'toast-success': toast.type === 'success',
                     'toast-error':   toast.type === 'error',
                     'toast-warning': toast.type === 'warning',
                     'toast-info':    toast.type === 'info',
                 }"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-full"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 translate-x-full"
                 role="alert">
                <div class="flex items-start gap-3">
                    <div class="toast-icon-wrap shrink-0">
                        <template x-if="toast.type === 'success'"><x-icon name="check-circle" class="w-5 h-5" /></template>
                        <template x-if="toast.type === 'error'"><x-icon name="x-circle" class="w-5 h-5" /></template>
                        <template x-if="toast.type === 'warning'"><x-icon name="exclamation-triangle" class="w-5 h-5" /></template>
                        <template x-if="toast.type === 'info'"><x-icon name="information-circle" class="w-5 h-5" /></template>
                    </div>
                    <p class="text-sm flex-1" x-text="toast.message"></p>
                    <button @click="$store.toast.remove(toast.id)" class="shrink-0 opacity-60 hover:opacity-100 transition-opacity" aria-label="Dismiss">
                        <x-icon name="x-mark" class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </template>
    </div>

    {{-- Lead import progress (global: survives navigation + syncs across tabs via localStorage) --}}
    <div x-show="$store.leadImport.panelVisible"
         x-cloak
         class="fixed bottom-4 right-4 z-[60] max-w-md w-[min(100vw-2rem,28rem)] shadow-xl rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-1)] text-[var(--color-on-surface)] overflow-hidden"
         style="display: none;"
         role="status"
         aria-live="polite">
        {{-- Collapsed strip --}}
        <button type="button"
                class="w-full flex items-center justify-between gap-2 px-3 py-2 text-left text-sm bg-[var(--color-surface-container-low)] hover:bg-[var(--color-surface-3)] transition-colors"
                x-show="$store.leadImport.collapsed"
                @click="$store.leadImport.toggleCollapsed()">
            <span class="flex items-center gap-2 min-w-0">
                <span class="inline-block size-2 rounded-full bg-[var(--color-primary)] shrink-0"
                      x-show="$store.leadImport.state && ['queued','processing'].includes($store.leadImport.state.status)"></span>
                <span class="truncate font-medium" x-text="$store.leadImport.track?.list_name || 'Lead import'"></span>
                <span class="text-xs text-[var(--color-on-surface-dim)] shrink-0" x-text="$store.leadImport.percentDisplay() != null ? $store.leadImport.percentDisplay() + '%' : '…'"></span>
            </span>
            <x-icon name="chevron-down" class="w-4 h-4 shrink-0 text-[var(--color-on-surface-dim)] -rotate-180" />
        </button>

        <div x-show="!$store.leadImport.collapsed" class="flex flex-col max-h-[min(70vh,32rem)]">
            <div class="px-3 py-2 border-b border-[var(--color-border)] flex items-start justify-between gap-2 bg-[var(--color-surface-container-low)]">
                <div class="min-w-0">
                    <div class="text-xs font-semibold text-[var(--color-on-surface)] truncate">Lead import</div>
                    <div class="text-[11px] text-[var(--color-on-surface-dim)] truncate" x-text="$store.leadImport.track?.list_name || ''"></div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <a x-show="$store.leadImport.track?.list_url"
                       :href="$store.leadImport.track?.list_url"
                       class="btn-icon"
                       title="Open list">
                        <x-icon name="arrow-top-right-on-square" class="w-4 h-4" />
                    </a>
                    <button type="button" class="btn-icon" @click="$store.leadImport.toggleCollapsed()" title="Minimize">
                        <x-icon name="chevron-down" class="w-4 h-4" />
                    </button>
                </div>
            </div>
            <div class="p-3 space-y-3 overflow-y-auto text-sm">
                <div class="flex items-center justify-between gap-2 text-xs text-[var(--color-on-surface-dim)]">
                    <span class="flex items-center gap-2">
                        <span class="inline-block size-1.5 rounded-full bg-[var(--color-primary)]"
                              x-show="$store.leadImport.state && ['queued','processing'].includes($store.leadImport.state.status)"></span>
                        <span x-text="$store.leadImport.statusLabel()"></span>
                    </span>
                </div>
                <div>
                    <div class="flex justify-between text-[11px] text-[var(--color-on-surface-dim)] mb-1">
                        <span>Progress</span>
                        <span x-show="$store.leadImport.percentDisplay() != null"><span x-text="$store.leadImport.percentDisplay()"></span>%</span>
                        <span x-show="$store.leadImport.isIndeterminate()">Estimating…</span>
                    </div>
                    <div class="h-2 rounded-full bg-[var(--color-border)] overflow-hidden">
                        <template x-if="$store.leadImport.percentDisplay() != null">
                            <div class="h-full rounded-full bg-[var(--color-primary)] transition-[width] duration-500 ease-out"
                                 :style="'width:' + Math.min(100, $store.leadImport.percentDisplay()) + '%'"></div>
                        </template>
                        <template x-if="$store.leadImport.isIndeterminate()">
                            <div class="h-full w-1/3 rounded-full bg-[var(--color-primary)]/80 animate-pulse"></div>
                        </template>
                    </div>
                    <p class="text-[11px] text-[var(--color-on-surface-dim)] mt-1"
                       x-show="$store.leadImport.state && $store.leadImport.state.estimated_rows > 0">
                        <span x-text="$store.leadImport.state.rows_processed ?? 0"></span> / <span x-text="$store.leadImport.state.estimated_rows"></span> rows
                    </p>
                </div>
                <div class="grid grid-cols-4 gap-1.5 text-center" x-show="$store.leadImport.state && $store.leadImport.state.status !== 'unknown'">
                    <div class="rounded-md bg-[var(--color-surface-container)] px-1 py-1.5">
                        <div class="text-sm font-semibold leading-none" x-text="$store.leadImport.state?.inserted ?? '—'"></div>
                        <div class="text-[9px] uppercase text-[var(--color-on-surface-dim)] mt-0.5">Ins</div>
                    </div>
                    <div class="rounded-md bg-[var(--color-surface-container)] px-1 py-1.5">
                        <div class="text-sm font-semibold leading-none" x-text="$store.leadImport.state?.updated ?? '—'"></div>
                        <div class="text-[9px] uppercase text-[var(--color-on-surface-dim)] mt-0.5">Upd</div>
                    </div>
                    <div class="rounded-md bg-[var(--color-surface-container)] px-1 py-1.5">
                        <div class="text-sm font-semibold leading-none" x-text="$store.leadImport.state?.skipped ?? '—'"></div>
                        <div class="text-[9px] uppercase text-[var(--color-on-surface-dim)] mt-0.5">Skip</div>
                    </div>
                    <div class="rounded-md bg-[var(--color-surface-container)] px-1 py-1.5">
                        <div class="text-sm font-semibold leading-none" x-text="$store.leadImport.state?.failed_chunks ?? '0'"></div>
                        <div class="text-[9px] uppercase text-[var(--color-on-surface-dim)] mt-0.5">Err</div>
                    </div>
                </div>
                <div x-show="$store.leadImport.state?.recent?.length">
                    <div class="text-[11px] font-medium text-[var(--color-on-surface-dim)] mb-1">Latest rows</div>
                    <ul class="text-xs space-y-0.5 max-h-28 overflow-y-auto font-mono">
                        <template x-for="(row, idx) in $store.leadImport.recentReversed" :key="(row.phone || '') + '-' + idx">
                            <li class="flex justify-between gap-2 border-b border-[var(--color-border)]/50 py-0.5 last:border-0">
                                <span class="truncate" x-text="row.phone"></span>
                                <span class="truncate text-[var(--color-on-surface-dim)]" x-text="row.name || '—'"></span>
                            </li>
                        </template>
                    </ul>
                </div>
                <div x-show="$store.leadImport.state?.status === 'failed'" class="rounded-lg bg-red-500/10 text-red-200 text-xs p-2">
                    <span x-text="$store.leadImport.state?.message || 'Error'"></span>
                </div>
                <div x-show="$store.leadImport.state?.status === 'unknown'" class="text-xs text-[var(--color-on-surface-dim)]">
                    <span x-text="$store.leadImport.state?.message || ''"></span>
                </div>
                <p x-show="$store.leadImport.error" class="text-xs text-amber-400" x-text="$store.leadImport.error"></p>
                <div class="flex flex-wrap gap-2 pt-1">
                    <button type="button"
                            class="btn-primary text-xs py-1.5 px-3"
                            x-show="$store.leadImport.isTerminal"
                            @click="$store.leadImport.dismiss()">
                        Dismiss
                    </button>
                    <span class="text-[11px] text-[var(--color-on-surface-dim)] self-center"
                          x-show="$store.leadImport.state?.status === 'completed'">
                        Page refreshes when you dismiss.
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Confirm dialog --}}
    <div x-show="$store.confirm.visible"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="modal-backdrop"
         style="display: none;"
         x-trap.noscroll="$store.confirm.visible">
        <div class="modal-box max-w-sm"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             @click.stop>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                         :class="$store.confirm.variant === 'danger' ? 'bg-red-500/15 text-red-400' : 'bg-amber-500/15 text-amber-400'">
                        <x-icon name="exclamation-triangle" class="w-5 h-5" />
                    </div>
                    <div>
                        <h3 class="font-semibold text-[var(--color-on-surface)]" x-text="$store.confirm.title"></h3>
                        <p class="text-sm text-[var(--color-on-surface-muted)] mt-1" x-text="$store.confirm.message"></p>
                    </div>
                </div>
            </div>
            <div class="px-6 pb-6 flex gap-3 justify-end">
                <button class="btn-ghost" @click="$store.confirm.decline()" x-text="$store.confirm.cancelText"></button>
                <button class="btn-danger"
                        :class="$store.confirm.variant !== 'danger' ? 'btn-primary' : ''"
                        @click="$store.confirm.accept()"
                        x-text="$store.confirm.confirmText">
                </button>
            </div>
        </div>
    </div>

    {{-- Global search overlay --}}
    <div x-show="$store.search.open"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="modal-backdrop"
         @click="$store.search.close()"
         style="display: none;"
         x-trap.noscroll="$store.search.open">
        <div class="search-overlay-box"
             @click.stop
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-data="globalSearch()">
            <div class="flex items-center gap-3 px-4 py-3 border-b border-[var(--color-border)]">
                <x-icon name="magnifying-glass" class="w-5 h-5 text-[var(--color-on-surface-muted)] shrink-0" />
                <input type="text"
                       x-model.debounce.300ms="query"
                       @input="search()"
                       @keydown.escape="$store.search.close()"
                       @keydown.arrow-down.prevent="focusNext()"
                       @keydown.arrow-up.prevent="focusPrev()"
                       placeholder="Search records, leads, users..."
                       class="flex-1 bg-transparent text-[var(--color-on-surface)] placeholder-[var(--color-on-surface-dim)] text-sm focus:outline-none"
                       x-ref="searchInput"
                       x-init="$nextTick(() => $el.focus())">
                <kbd class="search-kbd">ESC</kbd>
            </div>
            <div class="max-h-80 overflow-y-auto p-2">
                <template x-if="loading">
                    <div class="flex items-center justify-center py-8 gap-2 text-[var(--color-on-surface-dim)]">
                        <x-icon name="arrow-path" class="w-4 h-4 animate-spin" />
                        <span class="text-sm">Searching...</span>
                    </div>
                </template>
                <template x-if="!loading && query.length < 2 && $store.search.recent.length > 0">
                    <div>
                        <p class="text-xs font-semibold text-[var(--color-on-surface-dim)] uppercase tracking-wider px-3 py-2">Recent</p>
                        <template x-for="r in $store.search.recent" :key="r">
                            <button class="search-result-item w-full text-left" @click="useRecent(r)">
                                <x-icon name="clock" class="w-4 h-4 text-[var(--color-on-surface-dim)]" />
                                <span x-text="r" class="text-sm text-[var(--color-on-surface)]"></span>
                            </button>
                        </template>
                    </div>
                </template>
                <template x-if="!loading && results.length > 0">
                    <div>
                        <template x-for="(group, gk) in results" :key="gk">
                            <div class="mb-2">
                                <p class="text-xs font-semibold text-[var(--color-on-surface-dim)] uppercase tracking-wider px-3 py-1" x-text="group.label"></p>
                                <template x-for="item in group.items" :key="item.url">
                                    <a :href="item.url" class="search-result-item" @click="$store.search.addRecent(query)">
                                        <x-icon name="document-text" class="w-4 h-4 text-[var(--color-on-surface-dim)]" />
                                        <div class="min-w-0">
                                            <p class="text-sm text-[var(--color-on-surface)] truncate" x-text="item.title"></p>
                                            <p class="text-xs text-[var(--color-on-surface-dim)] truncate" x-text="item.subtitle ?? ''"></p>
                                        </div>
                                    </a>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
                <template x-if="!loading && query.length >= 2 && results.length === 0">
                    <div class="py-8 text-center text-sm text-[var(--color-on-surface-dim)]">
                        No results for "<span x-text="query" class="text-[var(--color-on-surface)]"></span>"
                    </div>
                </template>
            </div>
            <div class="border-t border-[var(--color-border)] px-4 py-2 flex items-center gap-3 text-xs text-[var(--color-on-surface-dim)]">
                <span><kbd class="search-kbd">↑↓</kbd> navigate</span>
                <span><kbd class="search-kbd">↵</kbd> open</span>
                <span><kbd class="search-kbd">ESC</kbd> close</span>
            </div>
        </div>
    </div>

    {{-- Click-to-call widget --}}
    @auth
    <x-click-to-call />
    @endauth

    {{-- Disposition modal (post-call wrap-up) --}}
    <div x-show="$store.call.state === 'wrapup'"
         class="modal-backdrop"
         style="display: none;"
         x-data="dispositionModal()"
         x-trap.noscroll="$store.call.state === 'wrapup'">
        <div class="modal-box max-w-md" @click.stop>
            <div class="modal-header">
                <h3 class="modal-title">Call Wrap-up — Disposition Required</h3>
            </div>
            <div class="modal-body space-y-4">
                <x-alert type="warning">
                    Select a disposition code before taking the next call.
                </x-alert>
                <div class="form-field">
                    <label class="form-label">Disposition Code <span class="text-[var(--color-danger)]">*</span></label>
                    <select x-model="selectedCode" class="form-select">
                        <option value="">-- Select --</option>
                        @foreach($dispositionCodes ?? [] as $dc)
                            <option value="{{ $dc->code }}">{{ $dc->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-field">
                    <label class="form-label">Notes</label>
                    <textarea x-model="notes" class="form-textarea" rows="3" placeholder="Optional call notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-primary" @click="submit()" :disabled="!selectedCode || submitting">
                    <x-icon name="check" class="w-4 h-4" />
                    <span x-text="submitting ? 'Saving...' : 'Save & Ready'">Save & Ready</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Session flash → Alpine toast (auto-trigger) --}}
    @if (session('success'))
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('toast').success(@js(session('success')));
            });
        </script>
    @endif
    @if (session('error'))
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.store('toast').error(@js(session('error')));
            });
        </script>
    @endif

    {{-- WebRTC remote audio element (hidden, managed by TelephonyCore) --}}
    @auth
    <audio id="remoteAudio" autoplay playsinline style="display:none;" aria-hidden="true"></audio>
    @include('partials.phone-widget')
    @php
        $telephonyBootstrap = session()->pull('telephony_bootstrap');
    @endphp
    <script>
        window.__telephonyBootstrap = @json($telephonyBootstrap);
        window.__telephonyMediaPath = @json(config('webrtc.media_path', 'sipjs'));
    </script>
    @endauth

    {{-- Global telephony: rehydrate call state + Echo subscription + SIP.js registration (persists across navigation) --}}
    @auth
    <script>
    document.addEventListener('alpine:init', function() {
        const store = Alpine.store('call');
        const userId = @js(auth()->id());

        function mapBackendToUI(status) {
            const map = { dialing: 'ringing', ringing: 'ringing', answered: 'connected', in_call: 'connected', on_hold: 'hold', transferring: 'connected', completed: 'wrapup', failed: 'idle', abandoned: 'idle' };
            return map[status] ?? 'idle';
        }

        function rehydrateFromStatus(res) {
            if (res.active && res.call) {
                store.state = mapBackendToUI(res.call.status);
                store.sessionId = res.call.session_id;
                store.number = res.call.phone_number || '';
                if (store.state === 'connected' && res.call.duration_seconds) {
                    store.duration = res.call.duration_seconds;
                    store.startTimer();
                }
            } else if (res.disposition_pending && res.pending_call) {
                store.state = 'wrapup';
                store.sessionId = res.pending_call.session_id;
                store.number = res.pending_call.phone_number || '';
            } else {
                store.state = 'idle';
                store.sessionId = null;
                store.number = '';
                store.stopTimer();
            }
        }

        (async function init() {
            store.state = 'idle';
            store.sessionId = null;
            store.number = '';
            store.stopTimer();
            try {
                const res = await window.axios.get('/api/call/status');
                if (res.data?.success) rehydrateFromStatus(res.data);
            } catch {}
            // VICIdial session iframe + reconnect/bootstrap: phone widget (phone-widget.js init)

            // 2. Subscribe Reverb channel for real-time state updates (agent route uses agentScreen() for a full subscription)
            if (window.TelephonyEcho?.initEcho && window.TelephonyEcho.isBroadcastEnabled()) {
                window.TelephonyEcho.initEcho();
                @unless(request()->routeIs('agent.index'))
                window.TelephonyEcho.subscribeAgentChannel(userId, (payload) => {
                    store.state = mapBackendToUI(payload.to_status);
                    if (payload.session_id) store.sessionId = payload.session_id;
                    if (payload.phone_number) store.number = payload.phone_number;
                    if (['completed', 'failed', 'abandoned'].includes(payload.to_status)) {
                        store.stopTimer();
                        store.state = 'wrapup';
                    } else if (['answered', 'in_call'].includes(payload.to_status)) {
                        store.startTimer();
                    }
                });
                @endunless
            }

            // 3. Register SIP.js with Asterisk SIP (WebRTC)
            //    Only when the media_path config allows it. ViciPhone-only deployments
            //    skip this so we don't have two WebRTC registrations for the same
            //    extension (documented: config/webrtc.php `media_path`).
            //    TelephonyCore is a singleton – calling register() when already
            //    registered is a no-op, so page navigation is safe.
            const mediaPath = window.__telephonyMediaPath || 'sipjs';
            if (window.TelephonyCore && (mediaPath === 'sipjs' || mediaPath === 'both')) {
                window.TelephonyCore.register().catch(err => {
                    console.warn('[TelephonyInit] SIP register error:', err);
                });
            }
            if (mediaPath === 'both') {
                console.warn('[TelephonyInit] media_path=both: both SIP.js and ViciPhone are active. Use only while migrating.');
            }
        })();
    });
    </script>
    @endauth

    {{-- Theme toggle: delegated clicks + fresh DOM queries so soft-nav (#main-layout swap) keeps working --}}
    <script>
      (function() {
        var html = document.documentElement;
        function applyTheme(t) {
          html.setAttribute('data-theme', t);
          try { localStorage.setItem('theme', t); } catch (e) {}
          var btn = document.getElementById('theme-toggle');
          var dark = btn && btn.querySelector('.theme-icon-dark');
          var light = btn && btn.querySelector('.theme-icon-light');
          if (dark) dark.classList.toggle('hidden', t === 'light');
          if (light) light.classList.toggle('hidden', t !== 'light');
          if (btn) {
            btn.setAttribute('aria-label', t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
            btn.setAttribute('title', t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
          }
        }
        document.addEventListener('click', function (e) {
          var toggle = e.target && e.target.closest && e.target.closest('#theme-toggle');
          if (!toggle) return;
          applyTheme(html.getAttribute('data-theme') === 'light' ? 'dark' : 'light');
        });
        applyTheme(html.getAttribute('data-theme') || 'dark');
        window.addEventListener('soft-navigate', function () {
          applyTheme(html.getAttribute('data-theme') || 'dark');
        });
      })();
    </script>

    {{-- Marker for soft-navigate.js: page-specific scripts from @stack follow this node --}}
    <div id="soft-nav-scripts-marker" class="hidden" aria-hidden="true"></div>
    @stack('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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
<body class="min-h-screen flex" style="margin: 0;" x-data x-cloak data-campaign="{{ session('campaign', 'mbsales') }}">

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
                            <form method="POST" action="{{ route('logout') }}">
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

    {{-- Click-to-call widget (agents) --}}
    @auth
    @if(auth()->user()->role === 'Agent' || auth()->user()->role === 'Team Leader')
    <x-click-to-call />
    @endif
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

    {{-- Theme toggle script --}}
    <script>
      (function() {
        var html  = document.documentElement;
        var btn   = document.getElementById('theme-toggle');
        var dark  = btn && btn.querySelector('.theme-icon-dark');
        var light = btn && btn.querySelector('.theme-icon-light');
        function applyTheme(t) {
          html.setAttribute('data-theme', t);
          try { localStorage.setItem('theme', t); } catch(e) {}
          if (dark)  dark.classList.toggle('hidden',  t === 'light');
          if (light) light.classList.toggle('hidden', t !== 'light');
          if (btn)   btn.setAttribute('aria-label', t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }
        if (btn) btn.addEventListener('click', () => {
          applyTheme(html.getAttribute('data-theme') === 'light' ? 'dark' : 'light');
        });
        applyTheme(html.getAttribute('data-theme') || 'dark');
      })();
    </script>

    @stack('scripts')
</body>
</html>

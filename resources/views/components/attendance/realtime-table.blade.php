@props([
    'pollSeconds' => null,
    'maxSessionHours' => null,
])

@php
    $pollSeconds ??= max(5, (int) config('attendance.realtime.poll_seconds', 15));
    $maxSessionHours ??= max(1, (int) config('attendance.realtime.max_session_hours', 24));
@endphp

<section
    {{ $attributes->class('attendance-realtime-panel md-card md-card--static mb-4 overflow-hidden') }}
    aria-labelledby="attendance-realtime-heading"
    data-endpoint="{{ route('api.attendance.realtime') }}"
    data-poll-seconds="{{ $pollSeconds }}"
    data-max-session-hours="{{ $maxSessionHours }}"
    x-data="attendanceRealtimeTable($el.dataset)"
    x-init="init()"
    :aria-busy="loading || refreshing"
>
    <div class="border-b border-[var(--color-border)] p-4 sm:p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 id="attendance-realtime-heading" class="text-base font-semibold text-[var(--color-on-surface)]">
                        Realtime Attendance Sessions
                    </h2>
                    <span class="badge badge-active">LIVE</span>
                </div>
                <p class="mt-1 max-w-3xl text-sm text-[var(--color-on-surface-muted)]">
                    Current logged-in staff and their active attendance state. Durations update live while the session is active.
                </p>
                <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">
                    Unmatched logins older than {{ $maxSessionHours }} hours are treated as stale and excluded.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs text-[var(--color-on-surface-dim)]">
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-flex h-2 w-2 rounded-full bg-[var(--color-success)]" aria-hidden="true"></span>
                    <span>Auto-refresh {{ $pollSeconds }}s</span>
                </span>
                <span class="hidden text-[var(--color-border-strong)] sm:inline" aria-hidden="true">•</span>
                <span x-text="updatedLabel()">Waiting for first refresh</span>
                <button
                    type="button"
                    class="btn-secondary text-xs"
                    @click="refresh()"
                    :disabled="refreshing || loading"
                >
                    <x-icon name="arrow-path" class="h-3.5 w-3.5" />
                    <span x-text="refreshing ? 'Refreshing…' : 'Refresh'">Refresh</span>
                </button>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]">Online</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-[var(--color-on-surface)]" x-text="stats.online">0</p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]">Available</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-[var(--color-success)]" x-text="stats.available">0</p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]">Away</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-[var(--color-warning)]" x-text="stats.away">0</p>
            </div>
            <div class="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]">Longest session</p>
                <p class="mt-1 text-lg font-bold tabular-nums text-[var(--color-on-surface)]" x-text="formatCompactDuration(stats.longest_session_seconds)">0m</p>
            </div>
        </div>

        <p
            x-show="error"
            x-cloak
            class="mt-3 text-xs text-[var(--color-warning)]"
            role="status"
            aria-live="polite"
            x-text="error"
        ></p>
    </div>

    <x-table.index class="attendance-realtime-table" caption="Current realtime staff attendance sessions">
            <thead>
                <tr>
                    <th scope="col">Staff</th>
                    <th scope="col">Session ID</th>
                    <th scope="col">Presence</th>
                    <th scope="col">Current State</th>
                    <th scope="col">Login At</th>
                    <th scope="col">Session Duration</th>
                    <th scope="col">State Duration</th>
                    <th scope="col">IP Address</th>
                </tr>
            </thead>
            <tbody x-show="loading" x-cloak>
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="flex items-center justify-center gap-2 py-4 text-sm text-[var(--color-on-surface-muted)]">
                            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />
                            Loading realtime attendance…
                        </div>
                    </td>
                </tr>
            </tbody>
            <tbody x-show="!loading && !error && sessions.length === 0" x-cloak>
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="py-4">
                            <x-icon name="users" class="mx-auto mb-2 h-8 w-8" />
                            <p class="font-medium text-sm">No active attendance sessions.</p>
                            <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Logged-in staff will appear here automatically.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tbody x-show="!loading && error && sessions.length === 0" x-cloak>
                <tr>
                    <td colspan="8" class="table-empty">
                        <div class="py-4">
                            <x-icon name="exclamation-triangle" class="mx-auto mb-2 h-8 w-8" />
                            <p class="font-medium text-sm">Realtime attendance is unavailable.</p>
                            <p class="mt-1 text-xs text-[var(--color-on-surface-dim)]">Use Refresh to try loading the current sessions again.</p>
                        </div>
                    </td>
                </tr>
            </tbody>
            <tbody x-show="!loading && sessions.length > 0" x-cloak>
                <template x-for="session in sessions" :key="session.session_id">
                    <tr>
                        <td>
                            <div class="min-w-44">
                                <div class="font-medium text-[var(--color-on-surface)]" x-text="session.name"></div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-[var(--color-on-surface-dim)]">
                                    <span class="font-mono" x-text="session.username ? '@' + session.username : '#' + session.user_id"></span>
                                    <span aria-hidden="true">•</span>
                                    <span x-text="session.role || 'Staff'"></span>
                                </div>
                            </div>
                        </td>
                        <td class="font-mono text-xs text-[var(--color-on-surface-muted)]" x-text="session.session_id"></td>
                        <td><span class="badge badge-active">ONLINE</span></td>
                        <td>
                            <span
                                class="badge"
                                :class="session.attendance_code === 'available' ? 'badge-active' : 'badge-warning'"
                                x-text="session.attendance"
                            ></span>
                        </td>
                        <td class="font-mono text-xs tabular-nums whitespace-nowrap" x-text="formatTime(session.login_at)"></td>
                        <td class="font-mono text-xs font-semibold tabular-nums whitespace-nowrap"
                            x-text="formatDuration(durationSince(session.login_at, session.session_duration_seconds))"></td>
                        <td class="font-mono text-xs tabular-nums whitespace-nowrap"
                            x-text="formatDuration(durationSince(session.status_since, session.status_duration_seconds))"></td>
                        <td class="font-mono text-xs text-[var(--color-on-surface-dim)]" x-text="session.ip_address || '—'"></td>
                    </tr>
                </template>
            </tbody>
    </x-table.index>
</section>

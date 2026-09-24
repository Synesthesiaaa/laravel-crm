<section
    {{ $attributes->class('md-card') }}
    aria-label="{{ __('Away status') }}"
    data-current-url="{{ route('api.attendance.current') }}"
    data-start-url="{{ route('api.attendance.start') }}"
    data-end-url="{{ route('api.attendance.end') }}"
    data-time-zone="{{ config('app.timezone') }}"
    x-data="attendanceStatusPanel($el.dataset)"
    x-init="init()"
    :aria-busy="(!ready || loading) ? 'true' : 'false'"
>
    <div class="flex flex-col gap-2 border-b border-[var(--color-border)] px-5 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-6">
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-[var(--color-on-surface)]">
                {{ __('Away status') }}
            </h3>
            <p class="mt-1 text-xs leading-5 text-[var(--color-on-surface-muted)]">
                {{ __('Set lunch, break, or another available status. Login and logout are recorded automatically.') }}
            </p>
        </div>
        <span
            class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-[var(--color-on-surface-dim)]"
            x-show="ready && !error"
            x-cloak
            role="status"
        >
            <span class="h-2 w-2 rounded-full" :class="open ? 'bg-[var(--color-info)]' : 'bg-[var(--color-success)]'"></span>
            <span x-text="open ? '{{ __('Away') }}' : '{{ __('Available') }}'"></span>
        </span>
    </div>

    <div class="p-5 sm:p-6">
        <div class="flex min-h-24 items-center gap-3 text-sm text-[var(--color-on-surface-muted)]" x-show="!ready" role="status">
            <x-icon name="arrow-path" class="h-4 w-4 animate-spin" />
            <span>{{ __('Loading attendance status...') }}</span>
        </div>

        <div
            class="mb-4 rounded-lg border border-[var(--color-danger)]/30 bg-[var(--color-danger-muted)] p-3"
            x-show="ready && error"
            x-cloak
            role="alert"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-start gap-2">
                    <x-icon name="exclamation-circle" class="mt-0.5 h-4 w-4 shrink-0 text-[var(--color-danger)]" />
                    <p class="text-sm text-[var(--color-on-surface)]" x-text="error"></p>
                </div>
                <button
                    type="button"
                    class="btn-secondary btn-xs shrink-0"
                    :disabled="loading"
                    :aria-busy="loading ? 'true' : 'false'"
                    @click="retry()"
                >
                    <x-icon name="arrow-path" class="h-3.5 w-3.5" />
                    {{ __('Retry') }}
                </button>
            </div>
        </div>

        <template x-if="ready && open">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="min-w-0">
                    <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-on-surface-dim)]">
                        {{ __('Current status') }}
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge type="info"><span x-text="open?.label"></span></x-badge>
                        <span class="text-sm text-[var(--color-on-surface-muted)]" x-show="open?.started_at">
                            {{ __('Since') }} <span x-text="formatStarted(open?.started_at)"></span>
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    class="btn-primary w-full sm:w-auto"
                    :disabled="loading"
                    :aria-busy="pendingAction === 'end' ? 'true' : 'false'"
                    @click="end()"
                >
                    <x-icon name="check" class="h-4 w-4" />
                    <span x-text="pendingAction === 'end' ? '{{ __('Ending...') }}' : '{{ __('End status') }}'"></span>
                </button>
            </div>
        </template>

        <template x-if="ready && !open && types.length">
            <div>
                <p class="mb-3 text-sm text-[var(--color-on-surface-muted)]">
                    {{ __('Choose a status when you step away from active work.') }}
                </p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                    <template x-for="type in types" :key="type.code">
                        <button
                            type="button"
                            class="btn-secondary justify-start text-sm"
                            :disabled="loading"
                            :aria-busy="pendingAction === 'start' ? 'true' : 'false'"
                            @click="start(type.code)"
                        >
                            <x-icon name="play" class="h-4 w-4 shrink-0" />
                            <span class="truncate" x-text="'{{ __('Start') }} ' + type.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="ready && !open && !types.length && !error">
            <x-empty-state
                icon="clock"
                title="{{ __('No away statuses available') }}"
                description="{{ __('There are no attendance statuses available for you to start right now.') }}"
                class="crm-empty-state--compact"
            />
        </template>
    </div>
</section>

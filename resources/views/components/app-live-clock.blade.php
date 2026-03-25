@props([
    'label' => 'Date/Time',
])

@php
    $appTz = config('app.timezone');
@endphp

<div {{ $attributes->class(['rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-elevated)] px-4 py-3']) }}
    x-data="{
        tz: @js($appTz),
        line: '',
        init() {
            const opts = {
                timeZone: this.tz,
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true,
                timeZoneName: 'short',
            };
            const tick = () => {
                try {
                    this.line = new Intl.DateTimeFormat(undefined, opts).format(new Date());
                } catch (e) {
                    this.line = new Date().toString();
                }
            };
            tick();
            setInterval(tick, 1000);
        },
    }">
    <p class="text-xs font-medium uppercase tracking-wide text-[var(--color-on-surface-muted)]">{{ $label }}</p>
    <p class="mt-1 font-mono text-sm font-semibold tabular-nums text-[var(--color-on-surface)]" x-text="line"></p>
    <p class="mt-1 text-xs text-[var(--color-on-surface-muted)]">
        <span class="text-[var(--color-on-surface-dim)]">Timezone:</span>
        <span x-text="tz" class="font-mono"></span>
    </p>
</div>

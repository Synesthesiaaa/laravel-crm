<script setup>
import { Head, router } from '@inertiajs/vue3';

defineProps({
    logs: { type: Array, default: () => [] },
    lastEvent: { type: Object, default: null },
    date: { type: String, default: '' },
});

function submitDate(e) {
    e.preventDefault();
    const fd = new FormData(e.target);
    router.get('/attendance', { date: fd.get('date') }, { preserveState: true });
}
</script>

<template>
    <div class="space-y-6">
        <Head title="My Attendance" />

        <div>
            <h1 class="text-lg font-semibold text-[var(--color-on-surface)]">My Attendance</h1>
            <p class="text-sm text-[var(--color-on-surface-muted)]">Your login and attendance events.</p>
        </div>

        <div class="md-hero mb-6">
            <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                <div class="min-w-0 flex-1">
                    <h2 class="text-lg font-bold text-[var(--color-on-surface)]">{{ $page.props.auth?.full_name ?? $page.props.auth?.username }}</h2>
                    <p class="mt-1 text-sm text-[var(--color-on-surface-muted)]">Your login and attendance events.</p>
                    <p v-if="lastEvent" class="mt-3 text-sm text-[var(--color-on-surface-muted)]">
                        Last event:
                        <span
                            class="ml-1 inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold"
                            :class="
                                lastEvent.event_type === 'login'
                                    ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400'
                                    : 'border-[var(--color-border)] bg-[var(--color-surface-2)]'
                            "
                        >
                            {{ String(lastEvent.event_type).toUpperCase() }}
                        </span>
                        <span class="ml-1">{{ lastEvent.event_time }}</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="md-card mb-4">
            <form class="flex items-end gap-4 p-4" @submit="submitDate">
                <div class="form-field">
                    <label class="form-label">Date</label>
                    <input class="form-input" type="date" name="date" :value="date" />
                </div>
                <div class="form-field">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn-primary">View</button>
                </div>
            </form>
        </div>

        <div class="overflow-x-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-card)]">
            <table class="w-full text-left text-sm">
                <thead
                    class="border-b border-[var(--color-border)] bg-[var(--color-surface-2)] text-xs font-semibold uppercase tracking-wider text-[var(--color-on-surface-dim)]"
                >
                    <tr>
                        <th class="px-4 py-3">Event</th>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--color-border)]">
                    <tr v-if="!logs?.length">
                        <td colspan="3" class="px-4 py-10 text-center text-sm text-[var(--color-on-surface-dim)]">No attendance events for this date.</td>
                    </tr>
                    <tr v-for="(log, i) in logs" v-else :key="i">
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold"
                                :class="
                                    log.event_type === 'login'
                                        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-400'
                                        : log.event_type === 'logout'
                                          ? 'border-[var(--color-border)] bg-[var(--color-surface-2)]'
                                          : 'border-[var(--color-info)]/40 bg-[var(--color-info-muted)] text-[var(--color-info-fg)]'
                                "
                            >
                                {{ String(log.event_type).toUpperCase() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-mono text-sm text-[var(--color-on-surface-muted)]">{{ log.event_time }}</td>
                        <td class="px-4 py-3 font-mono text-sm text-[var(--color-on-surface-dim)]">{{ log.ip_address ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

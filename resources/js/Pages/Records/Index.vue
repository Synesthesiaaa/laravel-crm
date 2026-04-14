<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/DataTable.vue';

const props = defineProps({
    history: { type: Object, default: () => ({ data: [] }) },
    filters: {
        type: Object,
        default: () => ({ start_date: '', end_date: '', agent: '' }),
    },
});

const rows = computed(() => props.history?.data ?? []);

function applyFilter(e) {
    const form = e.target;
    const fd = new FormData(form);
    router.get('/records', Object.fromEntries(fd.entries()), { preserveState: true, replace: true });
}

const hasFilters = computed(() => !!(props.filters?.start_date || props.filters?.end_date || props.filters?.agent));
</script>

<template>
    <div class="space-y-6">
        <Head title="Call History" />

        <div>
            <h1 class="text-lg font-semibold text-[var(--color-on-surface)]">Call History</h1>
            <p class="text-sm text-[var(--color-on-surface-muted)]">Your submitted records.</p>
        </div>

        <div class="md-card mb-4">
            <form class="flex flex-wrap items-end gap-4 p-4" @submit.prevent="applyFilter">
                <div class="form-field">
                    <label class="form-label">Start Date</label>
                    <input class="form-input" type="date" name="start_date" :value="filters.start_date" />
                </div>
                <div class="form-field">
                    <label class="form-label">End Date</label>
                    <input class="form-input" type="date" name="end_date" :value="filters.end_date" />
                </div>
                <div class="form-field">
                    <label class="form-label">Agent</label>
                    <input class="form-input" name="agent" :value="filters.agent" placeholder="Agent name" />
                </div>
                <div class="form-field">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn-primary">Filter</button>
                </div>
                <div v-if="hasFilters" class="form-field">
                    <label class="form-label">&nbsp;</label>
                    <Link href="/records" class="btn-ghost">Clear</Link>
                </div>
            </form>
        </div>

        <DataTable
            caption="Call history records"
            :columns="[
                { label: 'Date' },
                { label: 'Form' },
                { label: 'Agent' },
                { label: 'Phone' },
                { label: 'Status' },
            ]"
        >
            <template v-if="rows.length === 0">
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-sm text-[var(--color-on-surface-dim)]">
                        No call history found.
                    </td>
                </tr>
            </template>
            <tr v-for="row in rows" v-else :key="row.id ?? row.created_at + row.phone_number">
                <td class="whitespace-nowrap px-4 py-3 text-[var(--color-on-surface-muted)]">
                    {{ row.created_at }}
                </td>
                <td class="px-4 py-3">{{ row.form_type }}</td>
                <td class="px-4 py-3">{{ row.agent }}</td>
                <td class="px-4 py-3 font-mono text-sm">{{ row.phone_number ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full border border-[var(--color-success)]/30 bg-[var(--color-success-muted)] px-2 py-0.5 text-xs font-semibold text-[var(--color-success-fg)]">{{ row.status ?? 'RECORDED' }}</span>
                </td>
            </tr>
        </DataTable>

        <div v-if="history?.links?.length > 3" class="flex flex-wrap justify-center gap-2 py-4">
            <template v-for="(link, i) in history.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    class="min-w-[2rem] rounded-md border border-[var(--color-border)] px-3 py-1 text-center text-sm"
                    :class="{ 'border-[var(--color-primary)] font-semibold text-[var(--color-primary)]': link.active }"
                    preserve-scroll
                    v-html="link.label"
                />
                <span
                    v-else
                    class="min-w-[2rem] rounded-md border border-[var(--color-border)] px-3 py-1 text-center text-sm opacity-50"
                    v-html="link.label"
                />
            </template>
        </div>
    </div>
</template>

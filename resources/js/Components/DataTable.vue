<script setup>
defineProps({
    caption: { type: String, default: '' },
    columns: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="overflow-x-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-card)]">
        <table class="w-full text-left text-sm">
            <caption v-if="caption" class="sr-only">{{ caption }}</caption>
            <thead
                class="border-b border-[var(--color-border)] bg-[var(--color-surface-2)] text-xs font-semibold uppercase tracking-wider text-[var(--color-on-surface-dim)]"
            >
                <tr>
                    <th v-for="(col, i) in columns" :key="i" class="px-4 py-3">{{ col.label }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--color-border)]">
                <slot />
            </tbody>
        </table>
    </div>
</template>

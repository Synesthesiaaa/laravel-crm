<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';

const props = defineProps({
    markup: { type: String, required: true },
    title: { type: String, default: 'Admin' },
});

const root = ref(null);

function mountAlpine() {
    const el = root.value;
    if (!el) {
        return;
    }
    try {
        if (typeof window.Alpine?.destroyTree === 'function') {
            window.Alpine.destroyTree(el);
        }
    } catch {
        //
    }
    el.innerHTML = props.markup ?? '';
    nextTick(() => {
        try {
            window.Alpine?.initTree?.(el);
        } catch (e) {
            console.warn('[Admin/Bridge] Alpine.initTree failed', e);
        }
    });
}

onMounted(() => {
    mountAlpine();
});

watch(
    () => props.markup,
    () => {
        mountAlpine();
    },
);

onUnmounted(() => {
    const el = root.value;
    if (el && window.Alpine?.destroyTree) {
        try {
            window.Alpine.destroyTree(el);
        } catch {
            //
        }
    }
});
</script>

<template>
    <div>
        <Head :title="title" />
        <div ref="root" class="admin-inertia-bridge min-h-[120px]" />
    </div>
</template>

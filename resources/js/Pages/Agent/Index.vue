<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
const root = ref(null);

const telephonyFeatures = page.props.telephonyFeatures ?? {};

window.__agentScreenFeatures = telephonyFeatures;

watch(
    () => page.props.telephonyFeatures,
    (v) => {
        window.__agentScreenFeatures = v ?? {};
    },
    { deep: true },
);

function mountAlpine() {
    const el = root.value;
    if (!el || !window.Alpine) {
        return;
    }
    try {
        if (typeof window.Alpine.destroyTree === 'function') {
            window.Alpine.destroyTree(el);
        }
    } catch {
        //
    }
    el.innerHTML = page.props.agentMarkup ?? '';
    nextTick(() => {
        try {
            window.Alpine?.initTree?.(el);
        } catch (e) {
            console.warn('[Agent/Index] Alpine.initTree failed', e);
        }
    });
}

onMounted(() => {
    mountAlpine();
});

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

watch(
    () => page.props.agentMarkup,
    () => {
        mountAlpine();
    },
);
</script>

<template>
    <div>
        <Head title="Agent Screen" />
        <div ref="root" class="agent-inertia-host min-h-[200px]" />
    </div>
</template>

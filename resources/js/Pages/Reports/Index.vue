<script setup>
import { Head } from '@inertiajs/vue3';
import { onMounted, onUnmounted, nextTick, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const root = ref(null);

function mountAlpine() {
    const el = root.value;
    if (!el) {
        return;
    }
    try {
        window.Alpine?.destroyTree?.(el);
    } catch {
        //
    }
    el.innerHTML = page.props.reportsMarkup ?? '';
    nextTick(() => {
        try {
            window.Alpine?.initTree?.(el);
        } catch (e) {
            console.warn('[Reports/Index] Alpine.initTree failed', e);
        }
    });
}

onMounted(() => {
    mountAlpine();
});

watch(
    () => page.props.reportsMarkup,
    () => mountAlpine(),
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
        <Head title="Telephony Reports" />
        <div ref="root" class="reports-inertia-host min-h-[200px]" />
    </div>
</template>

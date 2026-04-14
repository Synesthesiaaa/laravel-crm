<script setup>
import { ref, shallowRef, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    options: { type: Object, required: true },
});

const el = ref(null);
const chart = shallowRef(null);

async function render() {
    if (!el.value || !props.options) {
        return;
    }
    const ApexCharts = await (window.ApexChartsLoader?.() ?? import('apexcharts').then((m) => m.default));
    chart.value?.destroy?.();
    chart.value = new ApexCharts(el.value, props.options);
    await chart.value.render();
}

onMounted(render);

watch(
    () => props.options,
    () => {
        render();
    },
    { deep: true },
);

onUnmounted(() => {
    chart.value?.destroy?.();
    chart.value = null;
});
</script>

<template>
    <div ref="el" />
</template>

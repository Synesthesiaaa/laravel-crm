import { ref, shallowRef, watch, onMounted, onUnmounted } from 'vue';

/**
 * Lazy ApexCharts via window.ApexChartsLoader (from app.js).
 */
export function useApexChart(containerRef, optionsRef) {
    const chart = shallowRef(null);

    const render = async () => {
        const el = containerRef.value;
        const opts = optionsRef.value;
        if (!el || !opts) {
            return;
        }
        const ApexCharts = await (window.ApexChartsLoader?.() ?? import('apexcharts').then((m) => m.default));
        if (!ApexCharts) {
            return;
        }
        chart.value?.destroy?.();
        chart.value = new ApexCharts(el, opts);
        await chart.value.render();
    };

    onMounted(() => {
        render();
    });

    watch(
        () => optionsRef.value,
        () => {
            render();
        },
        { deep: true },
    );

    onUnmounted(() => {
        chart.value?.destroy?.();
        chart.value = null;
    });

    return { chart };
}

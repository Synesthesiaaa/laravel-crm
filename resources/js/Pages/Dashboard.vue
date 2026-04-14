<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    forms: { type: Object, default: () => ({}) },
    activityTrend: { type: Object, default: () => ({ labels: [], values: [] }) },
    topAgents: { type: Object, default: () => ({ labels: [], values: [] }) },
});

const campaign = computed(() => page.props.campaign?.code ?? 'mbsales');
const campaignName = computed(() => page.props.campaign?.name ?? 'CRM');
const authUser = computed(() => page.props.auth);

const chartActivityEl = ref(null);
const chartAgentsEl = ref(null);
let chartActivity = null;
let chartAgents = null;

function themeChartOptions() {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const textColor = isDark ? '#a1a1aa' : '#52525b';
    const gridColor = isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.05)';
    const borderColor = isDark ? 'rgba(255,255,255,.07)' : 'rgba(0,0,0,.07)';
    return { isDark, textColor, gridColor, borderColor };
}

async function renderCharts() {
    const ApexCharts = await (window.ApexChartsLoader?.() ?? import('apexcharts').then((m) => m.default));
    if (!ApexCharts) {
        return;
    }

    const { isDark, textColor, gridColor, borderColor } = themeChartOptions();

    if (chartActivityEl.value && props.activityTrend?.labels?.length) {
        chartActivity?.destroy?.();
        chartActivity = new ApexCharts(chartActivityEl.value, {
            series: [{ name: 'Submissions', data: props.activityTrend.values ?? [] }],
            chart: { type: 'area', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif', animations: { enabled: true, easing: 'easeinout', speed: 600 } },
            colors: ['#e91e8c'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.03 } },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: props.activityTrend.labels ?? [], labels: { style: { colors: textColor, fontSize: '11px' }, rotate: -30 }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, min: 0 },
            grid: { borderColor: gridColor, strokeDashArray: 3 },
            tooltip: { theme: isDark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            theme: { mode: isDark ? 'dark' : 'light' },
        });
        await chartActivity.render();
    }

    if (chartAgentsEl.value && (props.topAgents?.labels ?? []).length) {
        chartAgents?.destroy?.();
        chartAgents = new ApexCharts(chartAgentsEl.value, {
            series: [{ name: 'Submissions', data: props.topAgents.values ?? [] }],
            chart: { type: 'bar', height: 220, toolbar: { show: false }, background: 'transparent', fontFamily: 'DM Sans, ui-sans-serif' },
            colors: ['#e91e8c'],
            plotOptions: { bar: { horizontal: true, borderRadius: 5, barHeight: '60%' } },
            xaxis: { labels: { style: { colors: textColor, fontSize: '11px' } }, axisBorder: { show: false } },
            yaxis: { labels: { style: { colors: textColor, fontSize: '11px' } } },
            grid: { borderColor: gridColor, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
            tooltip: { theme: isDark ? 'dark' : 'light' },
            dataLabels: { enabled: false },
            theme: { mode: isDark ? 'dark' : 'light' },
            categories: props.topAgents.labels ?? [],
        });
        await chartAgents.render();
    }
}

onMounted(() => {
    renderCharts();
    document.getElementById('theme-toggle')?.addEventListener('click', () => {
        setTimeout(renderCharts, 50);
    });
});

onUnmounted(() => {
    chartActivity?.destroy?.();
    chartAgents?.destroy?.();
});

watch(
    () => [props.activityTrend, props.topAgents],
    () => {
        renderCharts();
    },
    { deep: true },
);

const formEntries = computed(() => Object.entries(props.forms ?? {}));

const activitySum = computed(() => (props.activityTrend?.values ?? []).reduce((a, b) => a + Number(b || 0), 0));

const topAgentCount = computed(() => (props.topAgents?.labels ?? []).length);
</script>

<template>
    <div class="space-y-8">
        <Head title="Dashboard" />

        <div class="md-hero">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-[var(--color-on-surface)]">
                        Hello, {{ authUser?.full_name ?? authUser?.username }}
                    </h2>
                    <p class="mt-1 text-sm text-[var(--color-on-surface-muted)]">
                        Campaign:
                        <span class="font-semibold text-[var(--color-primary)]">{{ campaignName }}</span>
                    </p>
                </div>
                <span class="inline-flex items-center rounded-full border border-[var(--color-success)]/30 bg-[var(--color-success-muted)] px-2.5 py-0.5 text-xs font-semibold text-[var(--color-success-fg)]">Online</span>
            </div>
        </div>

        <div class="animate-stagger grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="stat-card">
                <div class="flex items-start justify-between">
                    <span class="stat-card-label">Active Forms</span>
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                        style="background: var(--color-primary-muted); color: var(--color-primary)"
                    >
                        <span class="text-xs font-bold">F</span>
                    </div>
                </div>
                <div class="stat-card-value">{{ formEntries.length }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-start justify-between">
                    <span class="stat-card-label">Campaign</span>
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                        style="background: var(--color-info-muted); color: var(--color-info)"
                    >
                        <span class="text-xs font-bold">C</span>
                    </div>
                </div>
                <div class="stat-card-value">{{ String(campaign).toUpperCase() }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-start justify-between">
                    <span class="stat-card-label">Activity (14d)</span>
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                        style="background: var(--color-success-muted); color: var(--color-success)"
                    >
                        <span class="text-xs font-bold">A</span>
                    </div>
                </div>
                <div class="stat-card-value">{{ activitySum }}</div>
            </div>
            <div class="stat-card">
                <div class="flex items-start justify-between">
                    <span class="stat-card-label">Top Agents</span>
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg"
                        style="background: var(--color-warning-muted); color: var(--color-warning)"
                    >
                        <span class="text-xs font-bold">T</span>
                    </div>
                </div>
                <div class="stat-card-value">{{ topAgentCount }}</div>
            </div>
        </div>

        <div v-if="activityTrend?.labels?.length" class="animate-stagger grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="chart-container lg:col-span-2">
                <p class="chart-title">Campaign Activity — Last 14 days</p>
                <div ref="chartActivityEl" style="min-height: 220px" />
            </div>
            <div class="chart-container">
                <p class="chart-title">Top Agents</p>
                <div ref="chartAgentsEl" style="min-height: 220px" />
            </div>
        </div>

        <div v-if="formEntries.length">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-widest text-[var(--color-on-surface-dim)]">Campaign Forms</h3>
            <div class="animate-stagger grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="[formCode, formConfig] in formEntries"
                    :key="formCode"
                    :href="`/forms/${formCode}`"
                    class="group md-card flex items-center gap-4 p-5 no-underline"
                >
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[var(--color-primary)] bg-[var(--color-primary-muted)] transition-transform group-hover:scale-105"
                    >
                        <span class="text-[var(--color-primary)]">Doc</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h4 class="truncate font-semibold text-[var(--color-on-surface)]">{{ formConfig?.name ?? formCode }}</h4>
                        <p class="mt-0.5 text-xs text-[var(--color-on-surface-dim)]">Submit new record</p>
                    </div>
                    <span class="shrink-0 text-[var(--color-on-surface-dim)] transition-colors group-hover:text-[var(--color-primary)]">→</span>
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <Link href="/records" class="group md-card flex items-center gap-3 p-4 no-underline">
                <span class="text-[var(--color-primary)]">📋</span>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">Call History</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)]">View submitted records</p>
                </div>
                <span class="text-[var(--color-on-surface-dim)] group-hover:text-[var(--color-primary)]">→</span>
            </Link>
            <Link href="/attendance" class="group md-card flex items-center gap-3 p-4 no-underline">
                <span class="text-[var(--color-primary)]">🕐</span>
                <div class="flex-1">
                    <h4 class="text-sm font-semibold text-[var(--color-on-surface)]">My Attendance</h4>
                    <p class="text-xs text-[var(--color-on-surface-dim)]">View login history</p>
                </div>
                <span class="text-[var(--color-on-surface-dim)] group-hover:text-[var(--color-primary)]">→</span>
            </Link>
        </div>
    </div>
</template>

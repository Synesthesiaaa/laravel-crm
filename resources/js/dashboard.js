const ACTIVITY_ENDPOINT = '/api/dashboard/activity';
const CHART_GROUP = 'dashboard';
const SUMMARY_ROOT_MARGIN = '0px';
const ACTIVITY_ROOT_MARGIN = '0px 0px 80px';
const FALLBACK_REFRESH_MS = 30_000;

let activeDashboard = null;

function dashboardPath(config) {
    try {
        return new URL(config?.dashboardPath || '/dashboard', window.location.origin).pathname;
    } catch (_) {
        return '/dashboard';
    }
}

function isDashboardUrl(url, config) {
    try {
        return new URL(url || window.location.href, window.location.origin).pathname === dashboardPath(config);
    } catch (_) {
        return window.location.pathname === dashboardPath(config);
    }
}

function normalizeConfig(config = {}) {
    return {
        campaign: String(config.campaign || ''),
        dashboardPath: String(config.dashboardPath || '/dashboard'),
        activityEndpoint: String(config.activityEndpoint || ACTIVITY_ENDPOINT),
        summaryDaily: Array.isArray(config.summaryDaily) ? config.summaryDaily : [],
        summaryCurrencySymbol: String(config.summaryCurrencySymbol || '₱'),
        summaryCurrentLabel: String(config.summaryCurrentLabel || 'Current period'),
        summaryPreviousLabel: String(config.summaryPreviousLabel || 'Previous period'),
        amountChartsEnabled: config.amountChartsEnabled === true,
    };
}

function chartElementIsActive(state, element) {
    return !state.disposed
        && Boolean(element)
        && Boolean(document.getElementById('main-layout')?.contains(element));
}

function destroyCharts() {
    window.crmCharts?.destroyGroup?.(CHART_GROUP);
}

function scheduleChartResize() {
    window.requestAnimationFrame?.(() => window.requestAnimationFrame?.(() => {
        window.resizeCrmDashboardCharts?.();
    }));
}

function setSummaryStatus(message) {
    const status = document.querySelector('[data-summary-chart-status]');
    if (status) {
        status.textContent = message;
    }
}

function setElementStatus(element, message) {
    if (!element) {
        return;
    }

    element.replaceChildren();
    const status = document.createElement('p');
    status.className = 'chart-empty-state';
    status.textContent = message;
    element.appendChild(status);
    element.setAttribute('aria-busy', 'false');
}

function readPrimaryColor() {
    return getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#b11267';
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}

function formatSummaryValue(state, value, mode = state.summaryMode) {
    const numericValue = Number(value) || 0;
    if (mode === 'amount') {
        return `${numericValue < 0 ? '-' : ''}${state.config.summaryCurrencySymbol}${Math.abs(numericValue).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }

    return Math.round(numericValue).toLocaleString();
}

function formatSummaryAxisValue(state, value, mode = state.summaryMode) {
    const numericValue = Math.abs(Number(value) || 0);
    if (mode !== 'amount' || numericValue < 1000) {
        return formatSummaryValue(state, value, mode);
    }

    const units = [[1_000_000_000, 'B'], [1_000_000, 'M'], [1_000, 'K']];
    const unit = units.find(([threshold]) => numericValue >= threshold);
    const scaled = numericValue / unit[0];

    return `${Number(value) < 0 ? '-' : ''}${state.config.summaryCurrencySymbol}${scaled.toFixed(1).replace(/\.0$/, '')}${unit[1]}`;
}

function formatSignedSummaryValue(state, value, mode = state.summaryMode) {
    const numericValue = Number(value) || 0;

    return `${numericValue > 0 ? '+' : numericValue < 0 ? '-' : ''}${formatSummaryValue(state, Math.abs(numericValue), mode)}`;
}

function summarySeries(state) {
    const key = state.summaryMode === 'amount' ? 'amount' : 'count';

    return [
        { name: state.config.summaryCurrentLabel, data: state.config.summaryDaily.map((point) => point.current[key]) },
        { name: state.config.summaryPreviousLabel, data: state.config.summaryDaily.map((point) => point.previous[key]) },
    ];
}

function summaryTooltip(state, { dataPointIndex }) {
    const point = state.config.summaryDaily[dataPointIndex];
    if (!point) {
        return '';
    }

    const mode = state.summaryMode;
    const key = mode === 'amount' ? 'amount' : 'count';
    const currentValue = Number(point.current[key]) || 0;
    const hasPreviousEquivalent = point.previous_date !== null;
    const previousValue = hasPreviousEquivalent ? Number(point.previous[key]) || 0 : null;
    const difference = hasPreviousEquivalent ? currentValue - previousValue : null;
    const comparison = !hasPreviousEquivalent
        ? 'No equivalent date'
        : previousValue === 0
            ? (currentValue === 0 ? 'No change vs last month' : 'New activity vs last month')
            : `${difference >= 0 ? '+' : ''}${((difference / previousValue) * 100).toFixed(2)}% vs last month`;
    const currentDate = escapeHtml(point.current_date);
    const previousDate = escapeHtml(point.previous_date || 'No equivalent date');
    const currentLabel = escapeHtml(state.config.summaryCurrentLabel);
    const previousLabel = escapeHtml(state.config.summaryPreviousLabel);
    const previousDisplay = hasPreviousEquivalent ? formatSummaryValue(state, previousValue, mode) : '—';
    const differenceDisplay = hasPreviousEquivalent ? formatSignedSummaryValue(state, difference, mode) : '—';

    return `<div class="px-3 py-2 text-xs" style="background: var(--color-surface-card); color: var(--color-on-surface);">
        <div class="font-semibold">Day ${escapeHtml(point.label)}</div>
        <div class="mt-2 flex justify-between gap-6"><span>${currentLabel} <span class="text-[var(--color-on-surface-dim)]">(${currentDate})</span></span><strong>${formatSummaryValue(state, currentValue, mode)}</strong></div>
        <div class="flex justify-between gap-6"><span>${previousLabel} <span class="text-[var(--color-on-surface-dim)]">(${previousDate})</span></span><strong>${previousDisplay}</strong></div>
        <div class="mt-2 border-t border-[var(--color-border)] pt-2"><span class="text-[var(--color-on-surface-dim)]">Difference</span> <strong>${differenceDisplay}</strong> <span class="text-[var(--color-on-surface-dim)]">${escapeHtml(comparison)}</span></div>
    </div>`;
}

function summaryChartOptions(state, chartConfig) {
    const amountMode = state.summaryMode === 'amount';
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    return {
        series: summarySeries(state),
        chart: {
            type: 'line',
            height: 300,
            width: '100%',
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'ui-sans-serif, system-ui, sans-serif',
            animations: { enabled: !reduceMotion, easing: 'easeinout', speed: 400 },
        },
        colors: [chartConfig.primaryColor, chartConfig.textColor],
        stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 6] },
        markers: { size: 3, strokeWidth: 0, hover: { size: 5 } },
        xaxis: {
            categories: state.config.summaryDaily.map((point) => point.label),
            labels: { style: { colors: chartConfig.textColor, fontSize: '11px' }, rotate: 0, hideOverlappingLabels: true },
            axisBorder: { show: false },
            axisTicks: { show: false },
            title: { text: 'Day of month', style: { color: chartConfig.textColor, fontSize: '11px', fontWeight: 500 } },
        },
        yaxis: {
            ...(amountMode ? {} : { min: 0 }),
            labels: { style: { colors: chartConfig.textColor, fontSize: '11px' }, formatter: (value) => formatSummaryAxisValue(state, value) },
            title: { text: amountMode ? `Amount (${state.config.summaryCurrencySymbol})` : 'Transactions', style: { color: chartConfig.textColor, fontSize: '11px', fontWeight: 500 } },
        },
        grid: { borderColor: chartConfig.gridColor, strokeDashArray: 3 },
        tooltip: { theme: chartConfig.isDark ? 'dark' : 'light', shared: false, intersect: true, custom: (options) => summaryTooltip(state, options) },
        dataLabels: { enabled: false },
        legend: { show: true, position: 'top', horizontalAlign: 'left', labels: { colors: chartConfig.textColor } },
        theme: { mode: chartConfig.isDark ? 'dark' : 'light' },
    };
}

function getChartConfig() {
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';

    return {
        isDark,
        primaryColor: readPrimaryColor(),
        textColor: isDark ? '#d4d4d8' : '#3f3f46',
        gridColor: isDark ? 'rgba(255,255,255,.05)' : 'rgba(0,0,0,.08)',
    };
}

async function loadApexCharts(state) {
    if (!state.apexChartsRequest) {
        state.apexChartsRequest = Promise.resolve(window.ApexChartsLoader?.())
            .then((ApexCharts) => ApexCharts || null)
            .catch(() => null);
    }

    return state.apexChartsRequest;
}

async function mountSummaryChart(state) {
    const element = document.getElementById('chart-dashboard-summary');
    if (!chartElementIsActive(state, element)) {
        return;
    }

    if (state.summaryMountStarted) {
        return;
    }

    state.summaryMountStarted = true;

    if (state.config.summaryDaily.length === 0) {
        setSummaryStatus('No daily comparison data is available.');
        element.setAttribute('aria-busy', 'false');
        return;
    }

    const ApexCharts = await loadApexCharts(state);
    if (!ApexCharts || !chartElementIsActive(state, element)) {
        element.querySelector('[data-summary-chart-loading]')?.remove();
        element.setAttribute('aria-busy', 'false');
        setSummaryStatus('Chart visualization is unavailable. Use the daily summary data table below.');
        return;
    }

    const chartConfig = getChartConfig();
    element.replaceChildren();
    state.summaryChartConfig = chartConfig;
    state.summaryChart = new ApexCharts(element, summaryChartOptions(state, chartConfig));
    window.crmCharts?.register?.(CHART_GROUP, 'chart-dashboard-summary', state.summaryChart);

    try {
        await state.summaryChart.render();
        if (!state.disposed) {
            element.setAttribute('aria-busy', 'false');
            setSummaryStatus('Comparison chart ready.');
            scheduleChartResize();
        }
    } catch (_) {
        state.summaryChart = null;
        element.replaceChildren();
        element.setAttribute('aria-busy', 'false');
        setSummaryStatus('Chart visualization is unavailable. Use the daily summary data table below.');
    }
}

async function loadActivity(state) {
    if (!state.activityRequest) {
        state.activityRequest = window.axios.get(state.config.activityEndpoint)
            .then(({ data }) => data?.activity || {})
            .catch(() => null);
    }

    return state.activityRequest;
}

async function mountAreaChart(state, ApexCharts, elementId, title, categories, values, chartConfig) {
    const element = document.getElementById(elementId);
    if (!chartElementIsActive(state, element)) {
        return;
    }

    if (!Array.isArray(categories) || categories.length === 0) {
        setElementStatus(element, `No ${title.toLowerCase()} data is available.`);
        return;
    }

    element.replaceChildren();
    const chart = new ApexCharts(element, {
        series: [{ name: 'Submissions', data: values }],
        chart: {
            type: 'area',
            height: 240,
            width: '100%',
            toolbar: { show: false },
            background: 'transparent',
            fontFamily: 'ui-sans-serif, system-ui, sans-serif',
            animations: { enabled: !window.matchMedia?.('(prefers-reduced-motion: reduce)').matches, easing: 'easeinout', speed: 600 },
        },
        colors: [chartConfig.primaryColor],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .35, opacityTo: .03 } },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: {
            categories,
            labels: { style: { colors: chartConfig.textColor, fontSize: '11px' }, rotate: -30 },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { labels: { style: { colors: chartConfig.textColor, fontSize: '11px' } }, min: 0 },
        grid: { borderColor: chartConfig.gridColor, strokeDashArray: 3 },
        tooltip: { theme: chartConfig.isDark ? 'dark' : 'light' },
        dataLabels: { enabled: false },
        theme: { mode: chartConfig.isDark ? 'dark' : 'light' },
    });

    window.crmCharts?.register?.(CHART_GROUP, elementId, chart);

    try {
        await chart.render();
        element.setAttribute('aria-busy', 'false');
    } catch (_) {
        setElementStatus(element, `${title} visualization is unavailable.`);
    }
}

async function mountActivityCharts(state) {
    if (state.activityMountStarted || state.disposed) {
        return;
    }

    state.activityMountStarted = true;

    const activity = await loadActivity(state);
    const elements = [
        document.getElementById('chart-daily-activity'),
        document.getElementById('chart-weekly-activity'),
        document.getElementById('chart-monthly-activity'),
    ];

    if (!activity || state.disposed) {
        elements.forEach((element) => setElementStatus(element, 'Activity data is temporarily unavailable.'));
        return;
    }

    const ApexCharts = await loadApexCharts(state);
    if (!ApexCharts || state.disposed) {
        elements.forEach((element) => setElementStatus(element, 'Chart visualization is unavailable.'));
        return;
    }

    const chartConfig = getChartConfig();
    await Promise.all([
        mountAreaChart(state, ApexCharts, 'chart-daily-activity', 'Daily activity', activity.daily?.labels, activity.daily?.values, chartConfig),
        mountAreaChart(state, ApexCharts, 'chart-weekly-activity', 'Weekly activity', activity.weekly?.labels, activity.weekly?.values, chartConfig),
        mountAreaChart(state, ApexCharts, 'chart-monthly-activity', 'Monthly activity', activity.monthly?.labels, activity.monthly?.values, chartConfig),
    ]);
    scheduleChartResize();
}

function summaryModeChanged(state, mode) {
    state.summaryMode = state.config.amountChartsEnabled && mode === 'amount' ? 'amount' : 'volume';
    if (!state.summaryChart || !state.summaryChartConfig) {
        return;
    }

    const options = summaryChartOptions(state, state.summaryChartConfig);
    Promise.all([
        state.summaryChart.updateSeries(options.series, true),
        state.summaryChart.updateOptions({ yaxis: options.yaxis, tooltip: options.tooltip }, false, true),
    ]).catch(() => {
        setSummaryStatus('Chart visualization is unavailable. Use the daily summary data table below.');
    });
}

function shouldDeferRefresh() {
    return document.hidden
        || Boolean(window.Alpine?.store('modal')?.open)
        || Boolean(window.Alpine?.store('confirm')?.visible)
        || Boolean(document.activeElement?.matches('input, select, textarea, [contenteditable="true"]'))
        || Date.now() - (activeDashboard?.lastInteractionAt || 0) < 1500;
}

function teardownLiveUpdates(state) {
    state.liveUpdatesStopped = true;
    document.removeEventListener('scroll', state.markInteraction, true);
    document.removeEventListener('pointerdown', state.markInteraction, true);
    document.removeEventListener('keydown', state.markInteraction, true);
    window.clearTimeout(state.refreshTimer);
    window.clearInterval(state.fallbackTimer);
    if (state.echoReadyHandler) {
        window.removeEventListener('telephony-echo:ready', state.echoReadyHandler);
    }
    state.dashboardTeardown?.();
}

function scheduleRefresh(state) {
    window.clearTimeout(state.refreshTimer);
    state.refreshTimer = window.setTimeout(() => {
        if (state.liveUpdatesStopped || state.refreshInFlight || typeof window.crmSoftNav?.refresh !== 'function') {
            return;
        }

        if (shouldDeferRefresh()) {
            scheduleRefresh(state);
            return;
        }

        state.refreshInFlight = true;
        Promise.resolve(window.crmSoftNav.refresh({ shouldDefer: shouldDeferRefresh }))
            .then((refreshed) => {
                if (refreshed === false && !state.liveUpdatesStopped) {
                    scheduleRefresh(state);
                }
            })
            .catch(() => {})
            .finally(() => {
                state.refreshInFlight = false;
            });
    }, 350);
}

function startLiveUpdates(state) {
    document.addEventListener('scroll', state.markInteraction, { capture: true, passive: true });
    document.addEventListener('pointerdown', state.markInteraction, { capture: true, passive: true });
    document.addEventListener('keydown', state.markInteraction, true);

    const initializeEcho = () => {
        state.echo = window.TelephonyEcho;
        if (!state.echo?.isBroadcastEnabled?.()) {
            return;
        }

        state.echo.initEcho?.();
        state.dashboardTeardown = state.echo.subscribeDashboardChannel?.(
            state.config.campaign,
            () => scheduleRefresh(state),
        ) || null;
    };

    if (window.TelephonyEcho) {
        initializeEcho();
    } else {
        state.echoReadyHandler = initializeEcho;
        window.addEventListener('telephony-echo:ready', state.echoReadyHandler, { once: true });
    }

    state.fallbackTimer = window.setInterval(() => {
        if (!(state.echo || window.TelephonyEcho)?.isEchoConnected?.()) {
            scheduleRefresh(state);
        }
    }, FALLBACK_REFRESH_MS);
}

function observeCharts(state) {
    const summaryElement = document.getElementById('chart-dashboard-summary');
    const activitySection = document.querySelector('[data-dashboard-section="activity"]');

    if (!('IntersectionObserver' in window)) {
        if (summaryElement && !state.summaryObservationStarted) {
            state.summaryObservationStarted = true;
            void mountSummaryChart(state);
        }
        if (activitySection && !state.activityObservationStarted) {
            state.activityObservationStarted = true;
            state.activityFallbackTimer = window.setTimeout(() => void mountActivityCharts(state), 1500);
        }
        return;
    }

    state.summaryObserver?.disconnect();
    state.activityObserver?.disconnect();
    state.summaryObserver = null;
    state.activityObserver = null;

    if (summaryElement && !state.summaryObservationStarted && !state.summaryMountStarted) {
        state.summaryObservationStarted = true;
        state.summaryObserver = new IntersectionObserver((entries, observer) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }
            observer.disconnect();
            void mountSummaryChart(state);
        }, { rootMargin: SUMMARY_ROOT_MARGIN });
        state.summaryObserver.observe(summaryElement);
    }

    if (activitySection && !state.activityObservationStarted && !state.activityMountStarted) {
        state.activityObservationStarted = true;
        state.activityObserver = new IntersectionObserver((entries, observer) => {
            if (!entries.some((entry) => entry.isIntersecting)) {
                return;
            }
            observer.disconnect();
            void mountActivityCharts(state);
        }, { rootMargin: ACTIVITY_ROOT_MARGIN });
        state.activityObserver.observe(activitySection);
    }
}

function teardownDashboard(state) {
    if (!state || state.disposed) {
        return;
    }

    state.disposed = true;
    state.summaryObserver?.disconnect();
    state.activityObserver?.disconnect();
    window.clearTimeout(state.activityFallbackTimer);
    window.removeEventListener('resize', state.resizeHandler);
    teardownLiveUpdates(state);
    window.crmSoftNav?.unregister?.(state.scope);
    destroyCharts();
    state.summaryChart = null;
}

function initDashboard(config = {}) {
    const normalized = normalizeConfig(config);
    const scope = window.crmSoftNav?.currentScope?.() || window.location.pathname;

    if (!isDashboardUrl(window.location.href, normalized)) {
        return;
    }

    if (activeDashboard && !activeDashboard.disposed
        && activeDashboard.main === document.getElementById('main-layout')) {
        activeDashboard.config = normalized;
        return;
    }

    teardownDashboard(activeDashboard);
    const state = {
        config: normalized,
        scope,
        main: document.getElementById('main-layout'),
        disposed: false,
        summaryMode: 'volume',
        summaryChart: null,
        summaryChartConfig: null,
        summaryMountStarted: false,
        activityMountStarted: false,
        summaryObservationStarted: false,
        activityObservationStarted: false,
        apexChartsRequest: null,
        activityRequest: null,
        summaryObserver: null,
        activityObserver: null,
        activityFallbackTimer: null,
        refreshTimer: null,
        fallbackTimer: null,
        refreshInFlight: false,
        liveUpdatesStopped: false,
        lastInteractionAt: 0,
        dashboardTeardown: null,
        echoReadyHandler: null,
    };
    state.markInteraction = () => { state.lastInteractionAt = Date.now(); };
    state.resizeHandler = scheduleChartResize;
    activeDashboard = state;

    window.setDashboardSummaryMode = (mode) => summaryModeChanged(activeDashboard || state, mode);
    window.addEventListener('resize', state.resizeHandler, { passive: true });
    window.crmSoftNav?.register?.(scope, {
        beforeSwap: () => teardownDashboard(state),
        afterSwap: () => {
            if (!state.disposed) {
                observeCharts(state);
            }
        },
    });
    startLiveUpdates(state);
    observeCharts(state);
}

window.crmDashboard = { init: initDashboard };

window.addEventListener('soft-navigate', (event) => {
    const config = window.__crmDashboardConfig || {};
    if (isDashboardUrl(event.detail?.url || window.location.href, config)) {
        initDashboard(config);
    }
});

if (window.__crmDashboardConfig) {
    initDashboard(window.__crmDashboardConfig);
}

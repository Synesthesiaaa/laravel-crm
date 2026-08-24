const defaultColors = {
    primary: '#e91e8c',
    success: '#22c55e',
    warning: '#f59e0b',
    danger: '#ef4444',
    info: '#3b82f6',
};

const defaultTokens = {
    colorScheme: 'dark',
    onSurface: '#fafafa',
    onSurfaceMuted: '#a1a1aa',
    onSurfaceDim: '#71717a',
    border: 'rgba(255,255,255,0.08)',
    fontFamily: "'DM Sans', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
};

export function createCrmChartTheme(tokens = {}, options = {}) {
    const palette = { ...defaultTokens, ...defaultColors, ...tokens };
    const fontFamily = palette.fontFamily || defaultTokens.fontFamily;
    const reducedMotion = options.reducedMotion === true;
    const chartOverrides = options.chart ?? {};
    const legendOverrides = options.legend ?? {};
    const tooltipOverrides = options.tooltip ?? {};

    return {
        colors: options.colors ?? [
            palette.primary,
            palette.success,
            palette.warning,
            palette.danger,
            palette.info,
            palette.onSurfaceMuted,
        ],
        chart: {
            background: 'transparent',
            foreColor: palette.onSurfaceMuted,
            fontFamily,
            height: 'auto',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: {
                enabled: !reducedMotion,
                easing: 'easeinout',
                speed: 350,
            },
            ...chartOverrides,
        },
        dataLabels: {
            enabled: false,
        },
        grid: {
            borderColor: palette.border,
            strokeDashArray: 4,
            padding: { left: 8, right: 8 },
        },
        legend: {
            position: 'bottom',
            horizontalAlign: 'left',
            fontFamily,
            labels: { colors: palette.onSurfaceMuted },
            itemMargin: { horizontal: 12, vertical: 4 },
            ...legendOverrides,
        },
        tooltip: {
            theme: palette.colorScheme === 'light' ? 'light' : 'dark',
            fillSeriesColor: false,
            style: { fontFamily },
            ...tooltipOverrides,
        },
        stroke: {
            curve: 'smooth',
            width: 2,
        },
        xaxis: {
            axisBorder: { color: palette.border },
            axisTicks: { color: palette.border },
            labels: { style: { colors: palette.onSurfaceDim } },
        },
        yaxis: {
            labels: { style: { colors: palette.onSurfaceDim } },
        },
        responsive: [{
            breakpoint: 640,
            options: {
                chart: { height: 220 },
                legend: { position: 'bottom', horizontalAlign: 'left' },
                xaxis: { labels: { rotate: -35, hideOverlappingLabels: true } },
            },
        }],
    };
}

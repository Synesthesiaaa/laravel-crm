import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { createCrmChartTheme } from '../../resources/js/chart-theme.js';

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

const darkTokens = {
    colorScheme: 'dark',
    primary: '#e91e8c',
    success: '#22c55e',
    warning: '#f59e0b',
    danger: '#ef4444',
    info: '#3b82f6',
    onSurface: '#fafafa',
    onSurfaceMuted: '#a1a1aa',
    onSurfaceDim: '#71717a',
    border: 'rgba(255,255,255,0.08)',
    surfaceCard: '#141414',
};

const chartViewPaths = [
    ['dashboard', 'resources', 'views', 'dashboard.blade.php'],
    ['admin-dashboard', 'resources', 'views', 'admin', 'dashboard.blade.php'],
    ['reports', 'resources', 'views', 'reports', 'index.blade.php'],
    ['supervisor', 'resources', 'views', 'admin', 'supervisor.blade.php'],
];

test('creates a semantic ApexCharts theme from CRM tokens', () => {
    const theme = createCrmChartTheme(darkTokens);

    assert.deepEqual(theme.colors.slice(0, 5), [
        '#e91e8c',
        '#22c55e',
        '#f59e0b',
        '#ef4444',
        '#3b82f6',
    ]);
    assert.equal(theme.chart.foreColor, '#a1a1aa');
    assert.equal(theme.grid.borderColor, 'rgba(255,255,255,0.08)');
    assert.equal(theme.tooltip.theme, 'dark');
});

test('disables chart animation for reduced-motion users', () => {
    const theme = createCrmChartTheme(darkTokens, { reducedMotion: true });

    assert.equal(theme.chart.animations.enabled, false);
    assert.equal(theme.chart.toolbar.show, false);
});

test('uses a light tooltip and readable neutral labels for light mode', () => {
    const theme = createCrmChartTheme({
        ...darkTokens,
        colorScheme: 'light',
        onSurfaceMuted: '#52525b',
        onSurfaceDim: '#71717a',
        border: 'rgba(0,0,0,0.08)',
    });

    assert.equal(theme.tooltip.theme, 'light');
    assert.equal(theme.legend.labels.colors, '#52525b');
    assert.equal(theme.xaxis.labels.style.colors, '#71717a');
});

test('chart-bearing views consume the shared CRM chart theme', () => {
    for (const [, ...segments] of chartViewPaths) {
        const view = fs.readFileSync(path.join(projectRoot, ...segments), 'utf8');

        assert.match(view, /crmChartTheme\?\.\(\)/, `missing shared chart theme in ${segments.at(-1)}`);
    }
});

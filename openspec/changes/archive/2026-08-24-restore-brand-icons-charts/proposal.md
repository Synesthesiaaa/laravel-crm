## Why

The recent responsive shell refresh changed the CRM's primary visual language from the established magenta/charcoal business palette to blue/navy, which no longer matches the system's existing brand and operational cues. Icons and ApexCharts also use inconsistent sizing, colors, and theme behavior across dashboards, reports, and supervisor views, increasing scanning effort and reducing visual cohesion.

## What Changes

- Restore the prior magenta/charcoal semantic palette while preserving the responsive and accessibility improvements already added to the shared shell.
- Establish consistent SVG icon sizing, stroke treatment, alignment, semantic color usage, and icon-only control behavior through the shared Blade icon component and visible shared UI surfaces.
- Add a shared ApexCharts theme contract driven by CSS semantic tokens for colors, typography, grid lines, tooltips, responsive sizing, and light/dark mode behavior.
- Apply the shared chart contract to user Dashboard, Admin Dashboard, Reports, and Supervisor charts without changing their data sources, filters, endpoints, or business calculations.
- Add regression tests and browser checks for theme switching, icon accessibility, chart rendering, and responsive layout.

## Capabilities

### New Capabilities

- `dashboard-visualizations`: Consistent, theme-aware, responsive ApexCharts presentation across CRM dashboards and reports.
- `brand-icon-system`: Consistent, accessible SVG icon presentation and semantic usage across shared CRM components.

### Modified Capabilities

- `responsive-crm-shell`: Restore the established brand palette while retaining responsive layout, focus, touch-target, and reduced-motion requirements.

## Impact

- Affected frontend files include `resources/css/app.css`, `resources/js/app.js`, the shared Blade icon/stat-card components, and chart-bearing Blade views under `resources/views`.
- No backend routes, database schema, chart data contracts, third-party dependencies, or authorization rules change.
- ApexCharts remains the existing chart renderer and continues to load dynamically through the current Vite entrypoint.

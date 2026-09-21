## Why

The Dashboard currently exposes daily, weekly, and month-to-date submission counts, while the existing amount view is limited to a selected business-day sales range. Management cannot therefore compare current performance with the equivalent elapsed period last month or see whether volume and monetary value are moving together. This change adds a concise, campaign-scoped executive summary using the application’s existing sales attribution rules.

## What Changes

- Add reusable current-period and previous-period calculation for month-to-date and completed-month comparisons, with safe handling of unequal month lengths.
- Aggregate qualifying sales/transactions and their authoritative attributed amount for both periods and each aligned day.
- Add centralized count and amount comparison values, including absolute variance, percentage variance, and safe zero-baseline messaging.
- Add four summary KPIs for current count, current amount, count change, and amount change, with period context and readable currency formatting.
- Replace the summary-only visualization with a Volume/Amount mode toggle that compares current and previous periods on separate units, while preserving the existing activity charts and dashboard layout controls.
- Add loading, empty, partial-comparison, accessible description, legend, tooltip, and responsive states without changing permissions, campaign scope, or existing sales configuration.
- Cover period arithmetic, attribution, filters/scope, daily alignment, missing days, comparisons, and rendered dashboard behavior with PHPUnit tests.

## Capabilities

### New Capabilities

- `dashboard-summary-comparison`: Campaign-scoped month-to-date/full-month count and amount summaries with daily current-versus-previous comparison and accessible dashboard presentation.

### Modified Capabilities

<!-- Existing requirements remain valid; the new executive summary is specified as a separate capability. -->

## Impact

- `DashboardController` and `DashboardStatsService` will provide the summary data to the existing Blade dashboard.
- A small period/comparison abstraction will own date range and variance rules.
- `resources/views/dashboard.blade.php` and its existing ApexCharts setup will gain summary cards and a two-mode comparison chart while retaining current activity/report sections.
- Existing campaign/form metadata, custom sales rules, marked amount fields, dashboard layout visibility/order, and live refresh behavior remain the source of truth.
- No schema or dependency changes are expected.

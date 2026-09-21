## Why

The dashboard currently exposes individual sales KPIs and a leaderboard, but it does not provide the compact daily-by-agent view used by operations to compare each configured form and the campaign total at a glance. Adding this report keeps the dashboard useful for day-to-day monitoring without forcing users to reconcile separate cards or reports.

## What Changes

- Add a campaign-aware daily report that groups the currently selected campaign's form submissions by agent.
- Show per-form daily amounts, per-form daily submission counts, and a total column for each agent.
- Show a month-to-date summary with account counts and submitted amounts using the same campaign and form configuration.
- Render the report with existing dashboard cards, typography, colors, and responsive table conventions; do not display the legacy “MPI Cards” label.
- Keep the report data-driven so campaigns with different forms and amount fields render their own columns.

## Capabilities

### New Capabilities

- `daily-campaign-report`: Daily and month-to-date campaign sales tables grouped by agent and configured form.

### Modified Capabilities

<!-- No existing capability requirements change. -->

## Impact

- `DashboardController` and `DashboardStatsService` for report data aggregation.
- `resources/views/dashboard.blade.php` and dashboard styles for the responsive report UI.
- Dashboard feature/unit tests for aggregation, campaign scoping, empty data, and rendering.
- No new dependencies, routes, or database migrations are required.

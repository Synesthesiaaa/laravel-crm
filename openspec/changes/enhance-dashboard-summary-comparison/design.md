## Context

The dashboard is a server-rendered Blade page. `DashboardStatsService` already owns campaign-scoped sales attribution for two modes: legacy numeric `form_fields.is_sale_amount` markers and custom campaign sales rules. The existing sales KPI query uses `created_at` and the existing activity charts aggregate form submissions, but there is no shared monthly period model or comparison result.

The summary must fit the current dense dashboard, preserve the existing pink/neutral token system and dashboard layout visibility controls, and update naturally through the existing soft-navigation/live-refresh path. No new database schema or dependency is needed.

## Goals / Non-Goals

**Goals:**

- Provide a single server-rendered summary data shape for current and equivalent previous monthly periods.
- Use the existing sales rule resolver as the only source of qualifying transaction and amount semantics.
- Aggregate current/previous totals and aligned daily values in one bounded read per configured form table.
- Make period arithmetic and zero-baseline comparison behavior reusable and independently testable.
- Add a compact, responsive, keyboard-operable Volume/Amount chart with exact tooltip values and a table alternative.

**Non-Goals:**

- Do not add a separate JSON endpoint when the existing dashboard convention passes data through Blade.
- Do not replace or reinterpret the existing rolling sales KPI, activity charts, campaign report, permissions, filters, or dashboard layout editor.
- Do not infer amounts from disposition JSON or unrelated columns; legacy summary amounts remain marked numeric fields and custom summary amounts remain configured rule fields.
- Do not add a migration or external charting dependency.

## Decisions

### 1. Add a small period/comparison service

Create `DashboardPeriodService` with methods for month-to-date and explicitly completed-month periods. It returns query boundaries, display labels, and aligned day limits. Month-to-date uses the current month start through the injected current timestamp; the previous boundary uses the same day number and time, capped to the previous month’s final valid day. Completed-month mode uses full calendar-month boundaries. The same service owns count/amount variance rules so division by zero and status labels are consistent.

Alternative considered: keeping Carbon arithmetic in the controller and chart service. Rejected because it would duplicate edge-case handling and make February/leap-year behavior harder to test.

### 2. Extend `DashboardStatsService` with one summary method

Add `getDashboardSummaryForCampaign()` accepting an optional `Carbon` observation time and an optional completed-month flag for deterministic tests. When the flag is omitted, the service identifies an observation at the end of a calendar month as a completed-month comparison. It resolves the current campaign’s existing sales rules, scans the union of the previous and current windows once per form table, and classifies each qualifying row into a current/previous bucket and day number. Null legacy amounts do not qualify; numeric zero and negative values follow the existing marked-field behavior. Custom rules reuse the existing trigger/condition semantics.

Alternative considered: calling the existing selected-range KPI methods multiple times. Rejected because it would rescan the same tables for totals and daily points and could produce mismatched attribution.

### 3. Keep the summary inside the existing KPI section

Render the new summary heading/cards/chart within the existing `kpis` layout section so saved dashboard layouts and their visibility behavior remain compatible. Existing activity charts and report tables are left unchanged.

### 4. Use a two-series ApexCharts line chart with a mode toggle

The chart contains two series only: the actual current month label and previous month label. Volume mode uses counts; Amount mode swaps both series and the y-axis formatter to currency. Shared tooltips show both exact values, absolute difference, and percentage when available. A visible legend, text period context, a screen-reader summary, and an expandable daily data table supplement color and hover interaction.

Alternative considered: dual y-axes for count and currency. Rejected because mixed units make magnitude comparisons misleading.

### 5. Make currency configuration explicit

Add environment-backed dashboard currency code/symbol settings with PHP/₱ defaults matching this Philippine CRM’s current business context. The view uses the configured symbol for compact/full values; no currency is embedded in aggregation logic.

## Risks / Trade-offs

- [Large form tables] → The summary reads only the previous/current bounded window, selects only attribution fields, and processes rows in chunks; no additional indexes are introduced without measured evidence.
- [Custom rules cannot be safely expressed as SQL aggregation] → Keep rule evaluation in PHP but perform one bounded scan per configured table.
- [ApexCharts loads dynamically] → Reserve chart space, expose a loading state, clear it after mount, and preserve the server-rendered KPI/table content if the chart library is unavailable.
- [Saved layouts predate this feature] → Keep the summary under the existing visible KPI section instead of introducing a new layout key.

## Migration Plan

1. Deploy the service, controller/view, component/config, and tests together.
2. No data migration is required; existing campaign form metadata and layouts remain valid.
3. Set `DASHBOARD_CURRENCY_CODE` and `DASHBOARD_CURRENCY_SYMBOL` only when a deployment needs values other than the defaults.
4. Roll back by reverting the code/config changes; stored dashboard layouts and form data are unaffected.

## Open Questions

None. The repository’s configured sales attribution rules are the authoritative amount definition, and the dashboard currently has no independent currency setting to preserve.

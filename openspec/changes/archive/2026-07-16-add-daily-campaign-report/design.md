## Context

The authenticated dashboard already resolves the active campaign, its configured forms, and date-scoped sales KPIs. Form tables contain an agent, business date, timestamps, and form-specific amount columns, while the dashboard's existing table/card styles are available through `md-card` and `md-table-wrap`. The new report must work for campaigns with different forms and must not hard-code the image's MBSales naming.

## Goals / Non-Goals

**Goals:**

- Aggregate the active campaign's valid form tables by agent for the current business day and month-to-date.
- Return stable, presentation-ready rows with per-form counts/amounts and totals.
- Render four compact, responsive dashboard tables: daily amounts, daily counts, MTD account totals, and MTD submitted amounts.
- Reuse existing cache invalidation, theme variables, table wrappers, and empty-state patterns.

**Non-Goals:**

- Adding a new route, migration, export, chart, or user-configurable date picker.
- Changing the existing sales KPI filters or the definition of marked sale amount fields.
- Reproducing the spreadsheet's “MPI Cards” label or fixed agent list.

## Decisions

1. **Use the active campaign's configured forms.** The service will resolve forms via the existing campaign repository and only query allow-listed tables/columns that exist. This keeps PJLI and future campaigns safe and avoids coupling to MBSales.
2. **Use `date` for daily and MTD grouping, with numeric amount columns from form metadata.** The daily report represents the business date shown in the source image, while existing sale fields define which numeric columns contribute amounts. Tables without a marked amount field still contribute counts and display a zero amount.
3. **Keep aggregation in `DashboardStatsService`.** It already owns dashboard aggregation, caching, schema validation, and cache invalidation. A single report method returns all four views so the controller performs one service call.
4. **Render as one responsive report section with four tables.** On wide screens, the four tables use a two-column grid; on small screens they stack and scroll horizontally through the existing wrapper. Headers use the dashboard's primary accent and dark/light theme variables, with labels “Daily amounts”, “Daily counts”, “Month to date accounts”, and “Month to date submitted amounts”.
5. **Cache by campaign and business date.** A short dashboard cache entry prevents four repeated scans during page refreshes. Existing `invalidate()` will forget the key, so submissions refresh the report through the established broadcast/invalidator path.

## Risks / Trade-offs

- [Large form tables can make PHP aggregation expensive] → Restrict queries to required columns/date ranges, aggregate per table with grouped queries where possible, and cache the result for the dashboard refresh interval.
- [A campaign may have no valid form tables or marked amount fields] → Return empty rows and zero totals while preserving form columns and the existing empty-state message.
- [Different campaigns have different form counts] → Build table headers and cells from the resolved form list instead of fixed columns; use horizontal overflow on narrow screens.

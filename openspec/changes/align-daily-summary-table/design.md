## Context

The dashboard summary is rendered by `resources/views/dashboard.blade.php`. The `DashboardStatsService` already returns period totals under `summary.current` and `summary.previous`, alongside the day-aligned records used by the table. The existing table renders the current period's volume and amount before the previous period's volume and amount, which separates the two values needed for a like-for-like comparison.

## Goals / Non-Goals

**Goals:**

- Present volume values as an adjacent current/previous pair.
- Present amount values as an adjacent current/previous pair when monetary table visibility is enabled.
- Show a semantic total row below the daily records using the existing period totals.
- Preserve unavailable previous-day markers and the existing responsive/accessibility behavior.

**Non-Goals:**

- Changing aggregation, attribution rules, date ranges, or API payloads.
- Adding a new component, dependency, or database change.
- Changing the chart series or KPI cards.

## Decisions

- Keep the implementation in the existing Blade table. This reuses the established table styles and avoids duplicating summary aggregation in the view or service.
- Render columns in the order `Day`, `Current volume`, `Previous volume`, `Current amount`, `Previous amount`. The amount pair remains inside the existing `amountVisible('tables')` condition so administrator display settings continue to control monetary data.
- Render a `<tfoot>` with a `Total` row. Current values come from `$summaryCurrent`; previous values come from `$summaryPrevious`. This keeps the row consistent with the service-level totals and includes all qualifying daily records, while a missing previous-day equivalent remains unavailable only in that daily row.
- Add a feature assertion that checks the header order and total values in the server-rendered dashboard response.

## Risks / Trade-offs

- [Risk] A wide table may require horizontal scrolling on small screens → Mitigation: retain the existing `md-table-wrap` container and compact table styling.
- [Risk] Amount columns may be hidden by campaign settings → Mitigation: render both amount headers, cells, and total cells under the same existing visibility condition.
- [Risk] View totals could drift from daily rows if aggregation changes later → Mitigation: source the total row from the service's existing `summary.current` and `summary.previous` totals rather than summing independently in Blade.

## Migration Plan

No migration or deployment data step is required. Deploy the view and test changes together; rollback is a normal code revert.

## Open Questions

None.

## Context

The historical Reports page still contains a commented-out `Campaign Comparison` section and client-side ApexCharts code that builds its data from the same campaign rows used by the active status and disposition sections. The requested change is limited to removing this inactive presentation; the reporting service must continue returning campaign-level rows because other report tables and API consumers use them.

## Goals / Non-Goals

**Goals:**

- Remove the inactive campaign-comparison markup and its chart registration/rendering path.
- Remove only state that exists solely to support that chart.
- Keep campaign scope selection, status breakdowns, disposition reporting, period comparison, and the API payload unchanged.
- Add a focused view regression assertion.

**Non-Goals:**

- Do not change report routes, controllers, services, database queries, or response contracts.
- Do not remove the separate historical period-comparison control or summary cards.
- Do not remove campaign rows used by status or disposition reporting.

## Decisions

- **Keep campaign rows in the normalized dashboard flow.** The same `campaigns` payload currently feeds `dashboard.status.rows` and top-status values, so only the chart-specific `dashboard.campaigns` projection will be removed.
- **Delete dead markup instead of leaving another HTML comment.** The inactive section is not user-facing and retaining it makes future maintenance ambiguous.
- **Remove the chart branch from `renderCharts()`.** The campaign chart element no longer exists, so its lookup, early-return condition, and `crmCharts` registration must no longer reference it.
- **Use a focused Blade view test.** A rendered Reports view assertion is sufficient to catch accidental reintroduction of the campaign-comparison heading or chart id without coupling tests to ApexCharts implementation details.

## Risks / Trade-offs

- [Risk] A future consumer may have depended on the chart’s client-side `dashboard.campaigns` state. → [Mitigation] That state is local to the Reports page and is not an API contract; keep the server/API campaign rows and active status/disposition projections intact.
- [Risk] Removing a chart can leave stale chart registrations after navigation. → [Mitigation] Remove the chart branch and its element lookup while retaining the existing shared chart-destroy lifecycle.

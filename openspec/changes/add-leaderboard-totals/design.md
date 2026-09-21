## Context

The user dashboard already calculates the selected-range sales count and amount in `DashboardStatsService` and exposes those values in `$kpis`. The same per-agent leaderboard is rendered in two places in `resources/views/dashboard.blade.php`: the Top Agent modal and the visible Agent leaderboard section. Both tables currently render only detail rows.

## Goals / Non-Goals

**Goals:**

- Show one aggregate `Total` row in both leaderboard tables.
- Keep the aggregate aligned with the selected date/time range and the existing qualifying marked-sale data.
- Preserve the existing sort order, empty state, and per-agent rows.

**Non-Goals:**

- No changes to sales calculation, ranking, routes, database schema, or API contracts.
- No new reusable component for a two-row presentation change.

## Decisions

- Compute the total count and amount from the rendered `$agentLeaderboard` rows in the Blade view. This keeps the footer visibly tied to the rows users see and avoids a second backend aggregation path. Using the existing KPI response would also be possible, but would make the table footer depend on a separate representation of the same data.
- Render the aggregate as a semantic `<tfoot>` after the existing `<tbody>` in both copies of the table. This keeps detail rows and summary data distinct for accessibility and styling.
- Format the count with `number_format()` and the amount with two decimal places, matching the existing row formatting.

## Risks / Trade-offs

- [Risk] The leaderboard could later be limited to a subset of agents while the footer still sums only displayed rows → Mitigation: the current sales leaderboard returns all qualifying agents, and future pagination/limits should define whether the footer is page-level or overall before changing this behavior.
- [Risk] The two table copies could drift → Mitigation: add a feature assertion that the rendered HTML contains two aggregate rows and verify both surfaces in the browser.

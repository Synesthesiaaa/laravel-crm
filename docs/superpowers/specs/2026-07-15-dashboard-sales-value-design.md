# Dashboard Sales Value Design

## Goal

Show both the sales count and total sale value in the existing rolling Sales KPI card.

## Behavior

The Sales card keeps the existing count as its primary value and adds a secondary `Total value: 0.00` line. The total uses the same configured rolling window as the count. Marked numeric form fields are authoritative when configured; otherwise the existing sale-disposition lead JSON amount fallback is used.

## Implementation

- Extend `DashboardStatsService::getKpisForCampaign` with a `sales_amount` float.
- Return count and amount together from field-driven rolling aggregation.
- Sum lead JSON sale amounts for fallback disposition rows.
- Add an optional secondary value to `x-stat-card` and render it only for the Sales card.
- Format the amount with two decimals, matching the existing leaderboard.

## Verification

- Add service tests for marked-field totals, zero/empty values, and disposition fallback totals.
- Add a dashboard view assertion for the secondary total value.
- Run Pint, focused PHPUnit tests, Vite build, and Playwright dashboard verification.

## Why

The dashboard's agent leaderboards show each agent's qualifying sales count and total sale amount, but do not show the combined result for the selected sales range. Users must add the rows manually to reconcile the leaderboard with the dashboard's overall Sales metric.

## What Changes

- Add a `Total` row to the visible agent leaderboard.
- Add the same aggregate row to the leaderboard modal.
- Show the combined qualifying sales count and total marked sale amount for the selected range.
- Preserve the existing agent ranking and per-agent values.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `field-sale-attribution`: The dashboard leaderboard presentation includes aggregate qualifying sales count and amount totals for the selected range.

## Impact

- `resources/views/dashboard.blade.php`: Render aggregate footer rows in both leaderboard tables.
- `tests/Unit/Services/DashboardStatsServiceTest.php`: Verify the leaderboard aggregates remain consistent with the selected-range sales metrics.
- No routes, APIs, database schema, or dependencies change.

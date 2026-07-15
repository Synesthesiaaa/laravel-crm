## Why

The dashboard currently shows only the number of sales in its rolling KPI window. Users also need the monetary total represented by those sales to evaluate recent performance without opening the month-to-date leaderboard.

## What Changes

- Return the rolling-window total sale amount alongside the existing sales count.
- Show both sales count and total sale value in the existing Sales KPI card.
- Calculate the amount from marked numeric form fields when configured, while preserving the existing disposition lead-data fallback.
- Add focused service and dashboard rendering coverage.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `field-sale-attribution`: expose the total amount for qualifying sales in the rolling dashboard KPI.

## Impact

- `DashboardStatsService` KPI return data and aggregation logic.
- Shared `x-stat-card` component and dashboard Blade view.
- Existing dashboard service tests and new view assertions.
- No schema or dependency changes.

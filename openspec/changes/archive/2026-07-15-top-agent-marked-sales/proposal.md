## Why

The 9-hour Top Agent KPI still reads disposition records even when marked form fields are the authoritative sales source. This leaves the card blank or inconsistent with the marked-sales count and amounts shown elsewhere on the dashboard.

## What Changes

- Rank the 9-hour Top Agent by qualifying marked sales when marked fields are configured.
- Expose the selected agent's qualifying sale count and summed sale value.
- Show those two values as the Top Agent card's secondary metric.
- Preserve the existing disposition-based Top Agent fallback when no marked fields are configured.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `field-sale-attribution`: align the rolling Top Agent KPI with marked sale submissions.

## Impact

- `DashboardStatsService` rolling agent aggregation and KPI return data.
- Dashboard Top Agent card rendering.
- Dashboard service and view tests.

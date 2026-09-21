## Why

Sales and Top Agent cards currently use the general 9-hour KPI window, which can omit valid sales from the prior day. These cards should represent a complete rolling day while keeping the Calls card's existing window unchanged.

## What Changes

- Add a separate configurable sales KPI window with a 24-hour default.
- Use that window for marked-sale count, total value, and Top Agent ranking.
- Label Sales and Top Agent cards with the 24-hour window.
- Preserve the existing Calls window and disposition fallback behavior.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `field-sale-attribution`: define the rolling window used by Sales and Top Agent metrics.

## Impact

- Dashboard configuration, KPI cache keys, and `DashboardStatsService` time filters.
- Dashboard card labels.
- Service tests and dashboard rendering verification.

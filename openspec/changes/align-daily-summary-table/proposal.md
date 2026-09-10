## Why

The expandable daily summary table currently places each period's volume and amount together, which makes the current-versus-previous comparison harder to scan. The table also omits a period total row even though the dashboard already calculates the matching totals.

## What Changes

- Reorder the summary table columns so current and previous volume values are adjacent, followed by current and previous amount values.
- Add an accessible `Total` row beneath the daily rows for current and previous volume and amount.
- Keep monetary columns governed by the existing dashboard amount-visibility setting.
- Add regression coverage for the rendered column order and totals.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `dashboard-summary-comparison`: clarify the daily summary table presentation and total-row behavior.

## Impact

- `resources/views/dashboard.blade.php` will change; no API, database, or aggregation changes are required.
- The existing dashboard summary feature test will assert the user-visible table structure.

## Why

Sales totals need to match the business day and show users exactly which configured forms contribute to the result. Disposition records are not an authoritative sale-value source and must not affect the dashboard sales cards or breakdown.

## What Changes

- Replace the rolling sales-card data source with qualifying values from numeric fields marked as sale amounts in Field Logic.
- Add a date and time-range filter, defaulting to the current date from 6:00 AM through 6:00 PM.
- Display a hover-accessible Sales modal with the selected range's total and a per-form sale count and amount breakdown.
- Ensure the selected filter drives the Sales card, Top Agent card, and breakdown from one server-side query range.
- Remove disposition-record fallback from Sales and Top Agent calculations.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `field-sale-attribution`: calculate dashboard sales exclusively from marked form fields over a user-selected business-day range and expose an auditable per-form breakdown.

## Impact

- Affected code: `DashboardController`, `DashboardStatsService`, dashboard Blade/Alpine behavior, and PHPUnit coverage.
- No new dependencies, API endpoint, or database schema are required.

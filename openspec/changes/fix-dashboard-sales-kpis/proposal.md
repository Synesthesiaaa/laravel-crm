## Why

The Sales amount and 24-hour Top Agent cards can remain empty because form submissions are stored without the timestamps their rolling queries require. The dashboard also continues to calculate fallback Top Agent data over nine hours while presenting that card as a 24-hour metric, and it still displays the obsolete Calls (9h) card.

## What Changes

- Persist submission timestamps when form data is stored so rolling sales metrics can include new qualifying submissions.
- Keep field-driven sales aggregation independent of telephony disposition storage and use the configured 24-hour sales window for fallback Top Agent ranking.
- Remove the Calls (9h) card from the main dashboard while retaining the Sales total-value and Top Agent cards.
- Add regression coverage for timestamped submissions, 24-hour fallback Top Agent data, and the dashboard card set.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `field-sale-attribution`: make rolling sales attribution reliable for newly stored form submissions and align all Top Agent variants with the sales window.

## Impact

- Affected code: `FormSubmissionService`, `DashboardStatsService`, the dashboard Blade view, and their PHPUnit coverage.
- No API, dependency, or database-schema changes.

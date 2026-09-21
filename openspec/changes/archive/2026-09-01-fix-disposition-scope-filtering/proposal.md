## Why

The Reports page exposes three disposition scopes, but changing the control does not refresh the report, and the server-side scope is not applied to the status breakdown derived from the same disposition data. Users can therefore continue seeing the unfiltered scope until a manual refresh and can see system statuses in other report sections.

## What Changes

- Refresh the historical report when the disposition scope changes.
- Apply the selected scope consistently to disposition Pareto data, disposition tables, report totals, funnel data, status totals, and campaign top-status values.
- Preserve the authoritative call totals and call-volume metrics independently of disposition classification.
- Add regression coverage for all, hide-system, and system-only scopes using configured system codes.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `telephony-operations-and-reporting`: Historical disposition scope filtering refreshes on selection and includes status breakdown and campaign status summaries.

## Impact

- Reports Blade/Alpine interaction.
- Historical telephony report parsing and aggregation.
- Historical telephony unit/feature tests and existing reporting specifications.
- No schema or dependency changes. System disposition codes remain deployment configuration via `VICI_REPORT_SYSTEM_DISPOSITION_CODES`.

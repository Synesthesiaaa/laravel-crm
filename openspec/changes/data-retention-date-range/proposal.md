## Why

Data Retention currently supports only an upper cutoff date, which prevents Super Admins from targeting a specific historical period. Adding an inclusive From/To range provides precise destruction scope while preserving the behavior of existing cutoff-only policies.

## What Changes

- Replace the policy cutoff date with a `to_date` and add a nullable `from_date` for legacy compatibility.
- Allow Super Admins to configure inclusive From and To dates for whole-record deletion or selected-field clearing.
- Apply lower and upper date predicates during scheduled cleanup, preserving records outside the range.
- Display From and To dates in the retention form and policy list.
- Validate date formats and reject ranges where From is later than To.
- Preserve existing form-specific field filtering, type-safe field clearing, and daily scheduling.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `data-retention`: Change retention policies from a single cutoff date to an inclusive From/To target range while retaining legacy cutoff behavior.

## Impact

Affected areas include the retention policy migration and model, request validation, admin controller and Blade view, scheduled cleanup service, data retention tests, and the existing data-retention OpenSpec capability.

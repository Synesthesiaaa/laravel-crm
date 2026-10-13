## Why

Deleting a required Field Logic field leaves a non-null storage column behind, so later submissions fail with SQL error 1364. Making an existing field optional likewise leaves storage rejecting empty values.

## What Changes

- Reconcile omitted legacy columns and optional active fields before inserting submissions.
- Preserve stored values, types, indexes, defaults, and system columns.
- Retain existing one-to-one legacy field mapping and required field validation.
- Cover deletion, optional edits, historical data, and repeat submissions with regression tests.

## Capabilities

### New Capabilities
- `form-storage-nullability`: Runtime reconciliation of form storage after field configuration changes.

### Modified Capabilities
None.

## Impact

FormSubmissionService and form submission tests. No new dependencies, routes, UI layout changes, destructive column removal, or bulk migrations.

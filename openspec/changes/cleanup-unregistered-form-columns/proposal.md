## Why

Some registered form tables contain legacy or manually added columns that are no longer part of the form definition. Data Master currently exposes those physical columns, and stale required columns can also break submissions even though users cannot enter them. This change establishes the form registry as the source of truth for form data columns.

## What Changes

- Add a migration that inspects every registered form table and drops physical columns that are not registered in `form_fields`.
- Preserve only the framework-generated columns `id`, `date`, `request_id`, `agent`, `created_at`, and `updated_at` in addition to registered form fields.
- Collect registered fields across all forms sharing a storage table before deciding which columns are safe to remove.
- Update Data Master column layout generation so unregistered physical columns are not appended to the displayed layout.
- Add regression coverage for cleanup, shared-table field preservation, and Data Master layouts.

## Capabilities

### New Capabilities

- `registered-form-schema-cleanup`: Keep form storage tables aligned with registered fields and approved framework columns.

### Modified Capabilities

- `data-master-desktop-table`: Define configured data columns as registered form fields plus approved framework-generated columns, rather than every physical table column.

## Impact

- Affected code: `DataMasterService`, form schema synchronization/migration logic, and related tests.
- Affected database: registered form storage tables may lose unregistered columns and their data when the migration runs.
- No API or dependency changes are required.

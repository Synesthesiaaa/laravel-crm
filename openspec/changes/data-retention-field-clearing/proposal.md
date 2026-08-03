## Why

The current retention policy can only delete complete form records. Some retention requirements instead require removing selected personal or financial values while preserving the submission record and unrelated fields. Super Admins need to choose the appropriate destruction scope per form without exposing arbitrary database tables or columns.

## What Changes

- Add whole-record and selected-field deletion modes to each form retention policy.
- Let Super Admins select multiple eligible fields for field-only cleanup.
- Preserve matching records and all unselected fields when selected-field cleanup runs.
- Apply type-safe clearing values based on each selected column's database type and nullability.
- Validate selected fields against the chosen form's metadata and storage schema.
- Display the saved mode and selected fields in the Data Retention tab.
- Keep scheduled cleanup, authorization, cutoff dates, deactivation, and existing whole-record behavior intact.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `data-retention`: Extend form retention policies and scheduled cleanup to support selected-field clearing in addition to complete-record deletion.

## Impact

- Database: extend `data_retention_policies` with deletion mode and selected-field configuration.
- Laravel models, request validation, controller, retention service, command tests, and admin Blade UI.
- Scheduled cleanup behavior gains a safe bulk-update branch for selected fields.
- No new dependencies or external APIs are required.

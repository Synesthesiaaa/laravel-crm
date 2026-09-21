# Data Retention Field Selection

## Context

The CRM already lets Super Admins configure one retention policy per active form. The current policy permanently deletes complete records whose `date` is on or before an explicit cutoff date. Form submissions are stored in per-form tables, while selectable form-field metadata is stored in `form_fields` and synchronized from each table schema.

Administrators also need a less destructive mode for data-retention requirements that require removing only selected personal or financial values while retaining the submission record and unrelated values.

## Goals

- Preserve the existing whole-record retention behavior.
- Add a selected-field mode that clears only administrator-selected form fields on records at or before the cutoff date.
- Preserve the matching record and every unselected field in selected-field mode.
- Derive selected-field choices from the currently selected form and exclude system/tracking columns.
- Apply type-safe clearing values based on each storage column's nullability and database type.
- Keep the feature restricted to Super Admins and prevent arbitrary table or column names from request input.

## Non-goals

- Restoring or archiving destroyed values.
- Selecting arbitrary tables or columns that are not registered for the chosen form.
- Deleting individual records manually from the retention screen.
- Changing the existing Field Logic behavior beyond retaining its automatic form filtering.
- Adding a relative retention-period mode; policies continue to use explicit calendar cutoff dates.

## User experience

The Data Retention tab keeps the existing form selector and cutoff-date input, and adds a deletion-mode selector:

1. `Delete entire records` permanently removes matching rows.
2. `Clear selected fields only` updates matching rows while retaining the row and all unselected columns.

When selected-field mode is active, the screen displays a checkbox list from the selected form's `form_fields` records. Each option shows its friendly field label and database field name. The list reloads automatically when the form changes, and saved selections are checked when editing an existing policy.

The destructive warning changes with the selected mode. Whole-record mode warns that complete records are permanently deleted. Selected-field mode explains that selected values are permanently cleared while records and unselected fields remain.

The configured-policy table displays the deletion mode and selected field names in addition to its existing campaign, form, storage table, cutoff, status, and run metadata columns.

## Data model

Extend `data_retention_policies` with:

- `deletion_mode` as a short string with a default of `whole_record`.
- `selected_fields` as nullable JSON, containing a list of validated database field names.

Existing policies remain whole-record policies through the database default. The `DataRetentionPolicy` model adds fillable attributes and casts `selected_fields` to an array. The existing one-policy-per-form unique constraint remains unchanged.

## Application flow

1. The configuration controller loads active forms with their retention policy and form fields, ordered consistently with the existing Field Logic page.
2. The retention form request validates the active form, cutoff date, deletion mode, and conditional selected-field list.
3. For selected-field mode, every submitted field must belong to the chosen form's active `FormField` metadata and exist in the form's registered storage table. System columns (`id`, `date`, timestamps, request/agent/lead/phone tracking fields) are not selectable.
4. The controller upserts the policy by `form_id`. Saving whole-record mode clears `selected_fields`; saving selected-field mode stores the validated list.
5. The scheduled `data-retention:run` command invokes the retention service for active policies.
6. Whole-record policies keep the current `DELETE ... WHERE date <= cutoff` behavior.
7. Selected-field policies build a validated column update map and execute one bulk update with the same cutoff predicate. The service does not modify `id`, the cutoff `date`, timestamps, or any unselected field.
8. The policy records the run timestamp and affected-record count only after the operation completes successfully.

Table and column identifiers are never trusted directly from request input. The table comes from the related `Form`; selected fields are checked against `FormField` metadata and the live schema before being passed to the query builder.

## Type-safe clearing

For each selected storage column, the service reads its type and nullability from Laravel's schema metadata:

- Nullable columns receive `NULL`.
- Non-null string, character, text, and blob-like columns receive an empty string.
- Non-null numeric columns receive `0`.
- Non-null boolean columns receive `0`.
- Non-null date/time columns are not eligible for selected-field mode because clearing them without a valid semantic date would violate the data contract. Nullable date/time columns receive `NULL`.
- Unsupported non-null column types cause the policy to be skipped and logged before any update is attempted.

This mapping keeps the operation valid for the existing MySQL form tables and the SQLite test schema while avoiding a generic value that could fail or change meaning across types.

## Authorization and validation

- Retention routes remain inside the existing Super Admin middleware group.
- The request requires `deletion_mode` to be one of `whole_record` or `selected_fields`.
- `selected_fields` is required and non-empty for selected-field mode and must be absent/ignored for whole-record mode.
- All selected names must be active fields for the selected form and valid columns in its registered storage table.
- System/tracking columns and unsafe identifiers are rejected.
- Validation errors return to the retention tab with the selected form, mode, and submitted checkbox values preserved.

## Error handling and safety

- Invalid storage, invalid field metadata, or unsupported type mappings skip the affected policy and log the reason without preventing other policies from running.
- The service validates the complete update map before issuing any selected-field update, preventing partial field clearing for a malformed policy.
- Whole-record deletion remains irreversible and is clearly labeled in the UI.
- Deactivation remains reversible and prevents future scheduled cleanup for that policy.

## Testing strategy

Add or update PHPUnit coverage for:

- Model casts and persistence of deletion mode and selected fields.
- Super Admin rendering of both modes and form-specific checkbox filtering.
- Creating and updating whole-record and selected-field policies.
- Rejection of empty, cross-form, system, or unsupported selected fields.
- Selected-field cleanup preserving the record and unselected values while clearing nullable, text, numeric, and boolean values type-safely.
- Whole-record cleanup remaining unchanged.
- Skipping invalid selected-field storage safely and continuing with other policies.
- Command output and scheduled command registration.

Browser verification will cover opening the Data Retention tab, switching forms, switching deletion modes, selecting fields, and confirming that only the selected form's fields are displayed.

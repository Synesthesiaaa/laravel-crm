# Data Retention Date Range Design

## Context

Data retention policies currently use a single `cutoff_date`. The cleanup job affects records whose `date` value is on or before that cutoff. Administrators need to target a bounded period while preserving the existing whole-record and selected-field destruction modes.

## Goals

- Allow administrators to specify an inclusive `From` and `To` date range.
- Affect only records whose `date` falls within the configured range.
- Preserve existing policies and their current cutoff behavior.
- Keep selected-field clearing type-safe and preserve every unselected field.
- Keep form-dependent field selection and the daily scheduler behavior unchanged.

## Non-goals

- No relative retention periods such as “older than 90 days”.
- No changes to the data form schema or the record date field.
- No changes to the available destruction modes.
- No changes to scheduler frequency or command invocation.

## Data model and migration

The existing `cutoff_date` column will be renamed to `to_date`. A nullable `from_date` column will be added to `data_retention_policies`.

The `from_date` column is nullable only for backward compatibility. Existing policies retain their current meaning: a null `from_date` means any record date up to and including `to_date`. No artificial earliest date will be written during migration.

The `DataRetentionPolicy` model will replace its `cutoff_date` fillable and cast entries with `from_date` and `to_date`.

## Cleanup behavior

For every active policy with a valid active form and data table, the retention query will apply:

- `date >= from_date` when `from_date` is present.
- `date <= to_date` for every policy.

Both boundaries are inclusive. Records outside the range must remain untouched.

Whole-record policies will continue to delete matching records. Selected-field policies will continue to update only the selected fields with the existing type-safe clearing values; the record and all unselected fields will remain.

The scheduler will continue running `data-retention:run` daily at its existing schedule.

## Validation and controller behavior

The retention policy request will validate `from_date` and `to_date` as `Y-m-d` dates and reject a range where `from_date` is later than `to_date`.

New or edited policies must provide both dates. Existing legacy policies may continue executing with a null `from_date` until an administrator edits and saves them with a range.

The controller will upsert the two dates and retain the existing deletion-mode, selected-field, and active-state behavior.

## Admin interface

The Data Retention tab will replace the cutoff-date input with:

- `From date` — the inclusive lower boundary.
- `To date` — the inclusive upper boundary.

Legacy policies will display `Any date` for the missing `From` value and their existing `To` date. The policy table will show separate From and To columns. Form-specific field filtering, selected-field controls, and mode warnings will remain unchanged.

## Testing and verification

Automated coverage will include:

- Records before, on, within, and after both range boundaries.
- Legacy policies with a null `from_date`.
- Validation failures for malformed dates and reversed ranges.
- Whole-record deletion and selected-field clearing within a range.
- Preservation of unselected fields and type-safe clearing values.
- Form-dependent field filtering and policy persistence.
- Migration/model date casting behavior.

The affected admin flow will also be verified in the browser, including the From/To inputs, validation feedback, selected-form field filtering, and policy list display.

## Risks and safeguards

- Renaming a live column can fail if deployment schema capabilities differ; the migration will use Laravel's schema operations and be tested against the project's configured database.
- Legacy policies must not broaden their cleanup scope; a null lower bound preserves the previous `date <= to_date` query exactly.
- Date validation and inclusive query predicates will be tested at every boundary to prevent off-by-one-day behavior.

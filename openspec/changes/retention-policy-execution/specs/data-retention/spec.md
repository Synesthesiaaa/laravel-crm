## MODIFIED Requirements

### Requirement: Super Admin retention policy management

The system SHALL provide a Data Retention tab under Super Admin System Configuration where an authorized administrator can create, update, and delete one retention policy per active form. Each policy SHALL support whole-record deletion or selected-field clearing, inclusive From/To dates, and either one-time or recurring execution. One-time execution SHALL support immediate Run Now and scheduled date/time; recurring execution SHALL support daily, weekly, and monthly schedules. The page SHALL show current execution status and due information.

#### Scenario: Super Admin views retention configuration

- **WHEN** an authenticated Super Admin opens `admin.configuration?tab=retention`
- **THEN** the page shows active forms, the retention policy form, From and To date inputs, execution mode controls, deletion-mode selector, current policy status, next run, last run, and a warning describing the selected destruction scope

#### Scenario: Selected-field mode lists only fields for the selected form

- **WHEN** a Super Admin selects field-only cleanup for an active form
- **THEN** the page shows multiple eligible fields belonging to that form and does not show system/tracking columns or fields from another form

#### Scenario: Non-Super Admin is denied

- **WHEN** an authenticated non-Super Admin requests the retention tab or submits, runs, or deletes a retention policy
- **THEN** the application returns the existing authorization failure response and does not create, execute, or delete a policy

#### Scenario: Whole-record policy is created for an active form

- **WHEN** a Super Admin submits an active form, whole-record mode, valid From and To dates, and valid one-time or recurring execution settings
- **THEN** the system creates one active policy for that form with no selected fields, calculates its next run, and redirects to the retention tab with a success message

#### Scenario: Selected-field policy is created for an active form

- **WHEN** a Super Admin submits an active form, selected-field mode, valid From and To dates, valid execution settings, and one or more eligible fields for that form
- **THEN** the system creates one field-clearing policy containing the selected field names, calculates its next run, and redirects to the retention tab with a success message

#### Scenario: Policy is updated for an existing form

- **WHEN** a Super Admin submits a form that already has a policy with new date range, destruction mode, selected-field list, execution mode, schedule, or active state
- **THEN** the system updates the existing policy instead of creating a duplicate and clears the field list when whole-record mode is selected

#### Scenario: Run Now executes one policy immediately

- **WHEN** a Super Admin confirms Run Now for a configured policy
- **THEN** the system executes only that policy immediately, records the result and affected count, deactivates a successful one-time policy, and preserves the recurring next-run schedule for a recurring policy

#### Scenario: Scheduled one-time policy deactivates after success

- **WHEN** an active one-time policy reaches its scheduled run date and time and execution succeeds
- **THEN** the system records successful run metadata, clears its next run, and deactivates the policy

#### Scenario: Failed one-time policy remains visible for administrator action

- **WHEN** a scheduled one-time policy fails or is skipped
- **THEN** the system records failed or skipped status and the error, leaves the policy configuration visible, and clears its next run so it is not retried continuously

#### Scenario: Policy configuration is deleted without deleting form data

- **WHEN** a Super Admin confirms Delete for a retention policy
- **THEN** the system deletes only the policy configuration and preserves all records in the form storage table

#### Scenario: Invalid form, date, range, mode, schedule, or field selection is rejected

- **WHEN** the submitted form is inactive/unregistered, either date is invalid, From is later than To, execution settings do not match the selected mode, the deletion mode is unsupported, selected-field mode has no fields, or a selected field is not eligible for that form
- **THEN** validation fails, the policy is unchanged, and the form returns with errors and submitted values

### Requirement: Automatic destruction of expired form records

The system SHALL run a scheduled `data-retention:run` command every minute and apply each active policy whose calculated `next_run_at` is due to records in the registered form table whose `date` is on or after `from_date` when present and on or before `to_date`. Both boundaries SHALL be inclusive. Whole-record policies SHALL permanently delete complete records; selected-field policies SHALL clear only the configured fields while preserving records and unselected fields.

#### Scenario: Due policies are processed and future policies are preserved

- **WHEN** the scheduled command runs with an active policy whose `next_run_at` is due and another active policy whose `next_run_at` is in the future
- **THEN** the command processes only the due policy and leaves the future policy's data and schedule unchanged

#### Scenario: Existing policies retain daily execution after migration

- **WHEN** an existing policy is migrated from the prior daily retention configuration
- **THEN** it is configured as recurring daily at 03:00 with a next daily occurrence and is not executed during migration

#### Scenario: Recurring schedules advance after execution

- **WHEN** a scheduled recurring policy completes successfully, fails, or is skipped
- **THEN** the policy remains active, records status and error metadata when applicable, and advances to the next daily, weekly, or monthly occurrence

#### Scenario: Records inside an inclusive range are destroyed for whole-record mode

- **WHEN** a due whole-record policy targets a storage table containing records dated before, on, within, and after its From/To range
- **THEN** the command permanently deletes records on both boundaries and inside the range and preserves records before From and after To

#### Scenario: Selected fields are cleared only inside the range

- **WHEN** a due selected-field policy targets records inside its inclusive From/To range with configured text, numeric, or nullable fields
- **THEN** the command clears only those configured fields using type-safe values, preserves each matching record and every unselected field, and preserves records outside the range

#### Scenario: Cleanup is limited to selected forms

- **WHEN** one form has a due active policy and another registered form has no due active policy
- **THEN** cleanup affects only the table and configured fields belonging to the due policy's form

#### Scenario: Cleanup records run metadata

- **WHEN** a policy cleanup completes successfully in either deletion mode
- **THEN** the policy stores the run timestamp, success status, affected count, and next execution when recurring

#### Scenario: Invalid storage or field mapping is skipped safely

- **WHEN** a due active policy references a form whose configured table is missing, lacks a `date` column, lacks a configured field, or contains an unsupported non-null selected column type
- **THEN** the command records skipped status and the error, skips that policy before mutation, advances recurring schedules safely, and continues processing other due policies

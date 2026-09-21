## MODIFIED Requirements

### Requirement: Super Admin retention policy management

The system SHALL provide a Data Retention tab under Super Admin System Configuration where an authorized administrator can create, update, and deactivate one retention policy per active form. Each policy SHALL support either whole-record deletion or selected-field clearing.

#### Scenario: Super Admin views retention configuration

- **WHEN** an authenticated Super Admin opens `admin.configuration?tab=retention`
- **THEN** the page shows active forms, the retention policy form, existing policy status, a deletion-mode selector, and a warning describing the selected destruction scope

#### Scenario: Selected-field mode lists only fields for the selected form

- **WHEN** a Super Admin selects field-only cleanup for an active form
- **THEN** the page shows multiple eligible fields belonging to that form and does not show system/tracking columns or fields from another form

#### Scenario: Non-Super Admin is denied

- **WHEN** an authenticated non-Super Admin requests the retention tab or submits a retention policy
- **THEN** the application returns the existing authorization failure response and does not create or change a policy

#### Scenario: Whole-record policy is created for an active form

- **WHEN** a Super Admin submits an active form, `whole_record` mode, and a valid `Y-m-d` cutoff date
- **THEN** the system creates one active whole-record policy for that form with no selected fields and redirects to the retention tab with a success message

#### Scenario: Selected-field policy is created for an active form

- **WHEN** a Super Admin submits an active form, `selected_fields` mode, a valid cutoff date, and one or more eligible fields for that form
- **THEN** the system creates one active field-clearing policy containing the selected field names and redirects to the retention tab with a success message

#### Scenario: Policy is updated for an existing form

- **WHEN** a Super Admin submits a form that already has a policy with a new cutoff date, deletion mode, selected-field list, or active state
- **THEN** the system updates the existing policy instead of creating a duplicate and clears the field list when whole-record mode is selected

#### Scenario: Invalid form, date, mode, or field selection is rejected

- **WHEN** the submitted form is inactive/unregistered, the cutoff date is invalid, the deletion mode is unsupported, selected-field mode has no fields, or a selected field is not eligible for that form
- **THEN** validation fails, the policy is unchanged, and the form returns with errors and submitted values

### Requirement: Automatic destruction of expired form records

The system SHALL run a scheduled `data-retention:run` command daily and apply each active policy to records in the registered form table whose `date` is on or before that policy's cutoff date. Whole-record policies SHALL permanently delete complete records; selected-field policies SHALL clear only the configured fields while preserving the record and all unselected fields.

#### Scenario: Records at or before the cutoff are deleted for whole-record mode

- **WHEN** an active whole-record policy targets a storage table containing records dated before, on, and after its cutoff
- **THEN** the command permanently deletes the records before and on the cutoff and preserves records after the cutoff

#### Scenario: Selected fields are cleared while records and other fields remain

- **WHEN** an active selected-field policy targets records before and on its cutoff with configured text, numeric, or nullable fields
- **THEN** the command clears only those configured fields using type-safe values, preserves each matching record, preserves every unselected field, and preserves records after the cutoff

#### Scenario: Cleanup is limited to selected forms

- **WHEN** one form has an active policy and another registered form has no active policy
- **THEN** cleanup affects only the table and configured fields belonging to the active policy's form

#### Scenario: Cleanup records run metadata

- **WHEN** a policy cleanup completes successfully in either deletion mode
- **THEN** the policy stores the run timestamp and number of affected records

#### Scenario: Invalid storage or field mapping is skipped safely

- **WHEN** an active policy references a form whose configured table is missing, lacks a `date` column, lacks a configured field, or contains an unsupported non-null selected column type
- **THEN** the command logs the problem, skips that policy before mutation, and continues processing other active policies

## MODIFIED Requirements

### Requirement: Super Admin retention policy management

The system SHALL provide a Data Retention tab under Super Admin System Configuration where an authorized administrator can create, update, and deactivate one retention policy per active form. Each policy SHALL support either whole-record deletion or selected-field clearing. New or edited policies SHALL provide valid inclusive `from_date` and `to_date` values; existing legacy policies with a null `from_date` SHALL remain executable until edited.

#### Scenario: Super Admin views retention configuration

- **WHEN** an authenticated Super Admin opens `admin.configuration?tab=retention`
- **THEN** the page shows active forms, the retention policy form, existing policy status, From and To date inputs, a deletion-mode selector, and a warning describing the selected destruction scope

#### Scenario: Selected-field mode lists only fields for the selected form

- **WHEN** a Super Admin selects field-only cleanup for an active form
- **THEN** the page shows multiple eligible fields belonging to that form and does not show system/tracking columns or fields from another form

#### Scenario: Non-Super Admin is denied

- **WHEN** an authenticated non-Super Admin requests the retention tab or submits a retention policy
- **THEN** the application returns the existing authorization failure response and does not create or change a policy

#### Scenario: Whole-record policy is created for an active form

- **WHEN** a Super Admin submits an active form, `whole_record` mode, valid `Y-m-d` From and To dates where From is not later than To
- **THEN** the system creates one active whole-record policy for that form with no selected fields and redirects to the retention tab with a success message

#### Scenario: Selected-field policy is created for an active form

- **WHEN** a Super Admin submits an active form, `selected_fields` mode, valid From and To dates where From is not later than To, and one or more eligible fields for that form
- **THEN** the system creates one active field-clearing policy containing the selected field names and redirects to the retention tab with a success message

#### Scenario: Policy is updated for an existing form

- **WHEN** a Super Admin submits a form that already has a policy with new From or To dates, a new deletion mode, a new selected-field list, or a new active state
- **THEN** the system updates the existing policy instead of creating a duplicate and clears the field list when whole-record mode is selected

#### Scenario: Legacy policy displays an unrestricted lower bound

- **WHEN** a Super Admin views an existing policy migrated from a cutoff-only policy with a null `from_date`
- **THEN** the policy displays `Any date` for From, displays the existing cutoff as To, and remains active with its previous cleanup scope

#### Scenario: Invalid form, date, range, mode, or field selection is rejected

- **WHEN** the submitted form is inactive/unregistered, either date is invalid, From is later than To, the deletion mode is unsupported, selected-field mode has no fields, or a selected field is not eligible for that form
- **THEN** validation fails, the policy is unchanged, and the form returns with errors and submitted values

### Requirement: Automatic destruction of expired form records

The system SHALL run a scheduled `data-retention:run` command daily and apply each active policy to records in the registered form table whose `date` is on or after `from_date` when present and on or before `to_date`. Both boundaries SHALL be inclusive. Whole-record policies SHALL permanently delete complete records; selected-field policies SHALL clear only the configured fields while preserving records and unselected fields.

#### Scenario: Records inside an inclusive range are deleted for whole-record mode

- **WHEN** an active whole-record policy targets a storage table containing records dated before, on, within, and after its From/To range
- **THEN** the command permanently deletes records on both boundaries and inside the range, preserves records before From and after To, and records the affected count

#### Scenario: Legacy policies retain upper-cutoff behavior

- **WHEN** an active whole-record policy has a null `from_date` and a `to_date`
- **THEN** the command deletes records on or before To and preserves records after To

#### Scenario: Selected fields are cleared only inside the range

- **WHEN** an active selected-field policy targets records inside its inclusive From/To range with configured text, numeric, or nullable fields
- **THEN** the command clears only those configured fields using type-safe values, preserves each matching record and every unselected field, and preserves records outside the range

#### Scenario: Cleanup is limited to selected forms

- **WHEN** one form has an active policy and another registered form has no active policy
- **THEN** cleanup affects only the table and configured fields belonging to the active policy's form

#### Scenario: Cleanup records run metadata

- **WHEN** a policy cleanup completes successfully in either deletion mode
- **THEN** the policy stores the run timestamp and number of affected records

#### Scenario: Invalid storage or field mapping is skipped safely

- **WHEN** an active policy references a form whose configured table is missing, lacks a `date` column, lacks a configured field, or contains an unsupported non-null selected column type
- **THEN** the command logs the problem, skips that policy before mutation, and continues processing other active policies

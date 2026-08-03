# Data Retention

## Purpose

Allow Super Admins to configure explicit per-form cutoff dates and automatically permanently delete complete form records covered by those policies.

## Requirements

### Requirement: Super Admin retention policy management

The system SHALL provide a Data Retention tab under Super Admin System Configuration where an authorized administrator can create, update, and deactivate one retention policy per active form.

#### Scenario: Super Admin views retention configuration

- **WHEN** an authenticated Super Admin opens `admin.configuration?tab=retention`
- **THEN** the page shows active forms, the retention policy form, existing policy status, and a warning that cleanup permanently deletes complete records

#### Scenario: Non-Super Admin is denied

- **WHEN** an authenticated non-Super Admin requests the retention tab or submits a retention policy
- **THEN** the application returns the existing authorization failure response and does not create or change a policy

#### Scenario: Policy is created for an active form

- **WHEN** a Super Admin submits an active form and a valid `Y-m-d` cutoff date
- **THEN** the system creates one active policy for that form and redirects to the retention tab with a success message

#### Scenario: Policy is updated for an existing form

- **WHEN** a Super Admin submits a form that already has a policy with a new cutoff date or active state
- **THEN** the system updates the existing policy instead of creating a duplicate

#### Scenario: Invalid form or date is rejected

- **WHEN** the submitted form does not reference an active registered form or the cutoff date is not a valid calendar date
- **THEN** validation fails, the policy is unchanged, and the form returns with errors and submitted values

### Requirement: Automatic destruction of expired form records

The system SHALL run a scheduled `data-retention:run` command daily and permanently delete complete records from each active policy's registered form table when the record `date` is on or before that policy's cutoff date.

#### Scenario: Records at or before the cutoff are deleted

- **WHEN** an active policy targets a storage table containing records dated before, on, and after its cutoff
- **THEN** the command permanently deletes the records before and on the cutoff and preserves records after the cutoff

#### Scenario: Cleanup is limited to selected forms

- **WHEN** one form has an active policy and another registered form has no active policy
- **THEN** cleanup affects only the table belonging to the configured form

#### Scenario: Cleanup records run metadata

- **WHEN** a policy cleanup completes successfully
- **THEN** the policy stores the run timestamp and number of deleted records

#### Scenario: Invalid storage is skipped safely

- **WHEN** an active policy references a form whose configured table is missing or lacks a `date` column
- **THEN** the command logs the problem, skips that policy, and continues processing other active policies

### Requirement: Form-specific Field Logic filtering

The Field Logic page SHALL reload its field list for the selected form automatically when the form selector changes.

#### Scenario: Changing the form refreshes its fields

- **WHEN** an administrator changes the Field Logic form selector
- **THEN** the page navigates with the selected form and displays only fields belonging to that form without requiring a separate Load button click

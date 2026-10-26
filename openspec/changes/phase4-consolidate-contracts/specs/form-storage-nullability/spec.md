## MODIFIED Requirements

### Requirement: Submissions reconcile deleted and optional storage fields
The system SHALL provision and reconcile storage during explicit form configuration or operator provisioning, not during an ordinary form or import submission. Provisioning SHALL preserve historical values, physical types, defaults, comments, collations, indexes, system columns, generated columns, and auto-increment columns while making removed non-system fields without defaults and active optional fields nullable. Ordinary submissions SHALL validate storage readiness and perform DML only; stale or missing storage SHALL produce a safe actionable failure without issuing DDL. Required-field validation and existing one-to-one legacy storage mapping SHALL remain intact.

#### Scenario: Delete a required field
- **WHEN** an administrator deletes a required Field Logic field and configuration provisioning completes
- **THEN** the record saves with NULL in the removed column and historical values remain unchanged

#### Scenario: Make an existing field optional
- **WHEN** an administrator makes a stored required field optional and configuration provisioning completes
- **THEN** the record saves with NULL while preserving the column's original type and default

#### Scenario: Retain required and legacy behavior
- **WHEN** a form contains active required fields or a one-to-one configured-to-storage mapping
- **THEN** required validation and mapped storage continue to work and system columns retain their constraints

#### Scenario: Repeat a submission after reconciliation
- **WHEN** a second valid record is saved after reconciliation
- **THEN** storage succeeds without schema introspection or another nullability ALTER

#### Scenario: Submission encounters stale storage
- **WHEN** a submission references a missing table or column, or a nullable field is not yet provisioned
- **THEN** it returns a safe provisioning error without creating or altering storage

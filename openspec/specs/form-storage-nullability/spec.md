# Form Storage Nullability

## Purpose

Keep form submissions working after Field Logic changes while preserving historical records and storage metadata.

## Requirements

### Requirement: Submissions reconcile deleted and optional storage fields
The system SHALL reconcile storage nullability before insertion so that removed non-system fields without defaults and active optional fields can be omitted or NULL. It SHALL preserve historical values, physical types, defaults, comments, collations, indexes, system columns, generated columns, and auto-increment columns. It SHALL retain required-field validation and existing one-to-one legacy storage mapping. Repeated submissions SHALL NOT alter already reconciled columns.

#### Scenario: Delete a required field
- **WHEN** an administrator deletes a required Field Logic field and submits the remaining fields
- **THEN** the record saves with NULL in the removed column and historical values remain unchanged

#### Scenario: Make an existing field optional
- **WHEN** an administrator makes a stored required field optional and submits it empty
- **THEN** the record saves with NULL while preserving the column's original type and default

#### Scenario: Retain required and legacy behavior
- **WHEN** a form contains active required fields or a one-to-one configured-to-storage mapping
- **THEN** required validation and mapped storage continue to work and system columns retain their constraints

#### Scenario: Repeat a submission after reconciliation
- **WHEN** a second valid record is saved after reconciliation
- **THEN** storage succeeds without another nullability ALTER

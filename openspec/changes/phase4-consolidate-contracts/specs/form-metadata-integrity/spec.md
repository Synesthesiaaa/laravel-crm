## ADDED Requirements

### Requirement: Used form identity remains stable
The system SHALL reject changes to a form's campaign code, form code, or storage table name when that form has field metadata or stored records. Descriptive properties such as name, icon, color, display order, and active state MAY still be edited. Before use, identifiers MAY be corrected through the existing form editor.

#### Scenario: Used form identity is changed
- **WHEN** an administrator submits a different campaign code, form code, or table name for a form with current or soft-deleted field metadata or stored rows
- **THEN** the update is rejected with a field-level validation message and the form and metadata remain connected

#### Scenario: Unused form identity is corrected
- **WHEN** an administrator changes identifiers for a form with no field metadata and no stored rows
- **THEN** the form is updated and its campaign configuration cache is invalidated

#### Scenario: Descriptive properties are edited
- **WHEN** an administrator changes only descriptive properties on a used form
- **THEN** the update succeeds without changing metadata keys or stored rows

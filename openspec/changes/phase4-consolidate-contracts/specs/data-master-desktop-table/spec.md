## ADDED Requirements

### Requirement: Data Master edits use configured field definitions
The system SHALL accept Data Master updates only for active field metadata belonging to the selected campaign and form. It SHALL exclude identifiers and provenance fields such as date, request ID, agent, lead ID, phone, and timestamps from general editing, and SHALL validate each accepted value with the same field-type and option rules as a normal form submission.

#### Scenario: Authorized editor changes a configured field
- **WHEN** an authorized administrator submits a valid value for an active configured field
- **THEN** the value is stored using its field-specific normalization and the record update event is emitted

#### Scenario: Request attempts a system or unregistered column update
- **WHEN** a Data Master request includes a physical column without active field metadata or a protected provenance column
- **THEN** the column is not written and the request receives a validation error for disallowed input

#### Scenario: Field value violates its configured type
- **WHEN** a submitted Data Master value violates its configured numeric, date, required, or option constraints
- **THEN** validation rejects the update before any record changes

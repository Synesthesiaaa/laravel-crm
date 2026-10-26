# agent-capture-webform-runtime Specification

## Purpose
TBD - created by archiving change phase2-restore-call-form-runtime-correctness. Update Purpose after archive.
## Requirements
### Requirement: Standalone Agent Capture starts Alpine once

The standalone Agent Capture page SHALL load Alpine from its dedicated Vite entry, expose the runtime before Alpine evaluates page components, and initialize the capture component once with its server-provided initial field values.

#### Scenario: Authenticated agent opens a configured capture form
- **WHEN** the standalone capture page loads its production Vite entry
- **THEN** Alpine initializes the form, removes `x-cloak` from configured visible fields, and binds the provided initial values
- **AND** the page does not depend on the shared CRM layout bundle

#### Scenario: Capture form saves without live telephony
- **WHEN** an agent submits valid capture data in an isolated browser test
- **THEN** the existing capture endpoint stores the allowed data and returns success
- **AND** no VICIdial or Asterisk command is issued by the browser test

### Requirement: Agent Capture visibility validation is server-authoritative

The Agent Capture API SHALL evaluate configured visibility rules from submitted field values and SHALL require only fields that are both configured required and visible under those rules. Client-supplied `visible_fields` SHALL NOT decide validation or persistence.

#### Scenario: Hidden required capture field is omitted
- **WHEN** a required field's visibility rule evaluates false from the submitted source value
- **THEN** the API accepts its omission
- **AND** it does not persist a client-supplied hidden value for that field

#### Scenario: Client lies about visible fields
- **WHEN** a client omits a required visible field from `visible_fields`
- **THEN** server-side visibility evaluation still rejects the submission

#### Scenario: Required visible capture field is missing
- **WHEN** a required field's visibility rule evaluates true and its value is empty
- **THEN** the API returns a field validation error


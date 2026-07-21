## ADDED Requirements

### Requirement: Numeric request IDs for new form submissions
The system SHALL assign every new successful form submission a request ID composed of a `YYYYMMDDHHMMSS` timestamp prefix followed by exactly six cryptographically secure random decimal digits. It SHALL ignore any client-supplied request ID.

#### Scenario: Submit a valid form record
- **WHEN** an authenticated agent submits a valid form record
- **THEN** the stored request ID matches a 20-digit numeric value beginning with the submission timestamp prefix

#### Scenario: Client supplies a request ID
- **WHEN** an agent submits a form with a client-provided request ID
- **THEN** the stored record uses a newly generated numeric request ID instead of the client value

### Requirement: Per-form request ID collision protection
The system SHALL verify that a generated request ID is unused in the target form table and SHALL generate a replacement candidate when a collision is found. It SHALL fail the submission without persisting a record if it cannot produce an unused candidate within the configured retry limit.

#### Scenario: Candidate request ID already exists
- **WHEN** a generated candidate matches an existing request ID in the target form table
- **THEN** the system generates and checks another candidate before inserting the submission

#### Scenario: Existing records retain historical identifiers
- **WHEN** the numeric request-ID change is deployed
- **THEN** request IDs on already stored records remain unchanged

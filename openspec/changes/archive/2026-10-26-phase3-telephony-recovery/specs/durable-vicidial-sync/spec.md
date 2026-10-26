## ADDED Requirements

### Requirement: Remote writes are not blindly replayed
The system SHALL classify VICIdial operations by idempotency and SHALL NOT automatically retry a non-idempotent write after an ambiguous transport failure.

#### Scenario: Lead creation loses its response
- **WHEN** `add_lead` is sent and its transport response is ambiguous
- **THEN** the system reports an unknown outcome and does not resend the request automatically

#### Scenario: Read-only lookup has a transient failure
- **WHEN** a classified read-only operation receives a retryable transport failure
- **THEN** the system may retry it within the configured bounded retry policy

### Requirement: Local telephony writes retain synchronization intent
The system SHALL persist each required remote disposition or lead-field synchronization intent in the same database transaction as the associated local business record.

#### Scenario: Local disposition commits
- **WHEN** a disposition and its related call state commit successfully
- **THEN** a uniquely keyed outbox record for the matching remote disposition exists in the same committed transaction

#### Scenario: Local capture write rolls back
- **WHEN** an Agent Capture write fails before commit
- **THEN** neither its local business record nor its outbox record is committed

### Requirement: Synchronization attempts are durable and outcome-aware
The system SHALL process outbox records with persisted attempt state and SHALL distinguish success, definitive failure, pre-dispatch queue failure, and uncertain remote outcome. Once a remote write begins, the system SHALL NOT automatically replay it after a failure.

#### Scenario: Remote update succeeds
- **WHEN** the remote operation returns a definitive success
- **THEN** the outbox record is marked synced and is not dispatched again

#### Scenario: Remote write outcome is ambiguous
- **WHEN** a write may have reached VICIdial but its response is lost or the worker terminates during the request
- **THEN** the record is marked unknown and excluded from automatic replay

#### Scenario: Queue dispatch fails before the worker claims the record
- **WHEN** the local queue cannot accept the job before any remote request begins
- **THEN** the record remains pending and the scheduled dispatcher may dispatch it again

#### Scenario: Two workers claim one record
- **WHEN** concurrent workers attempt to claim the same pending outbox record
- **THEN** at most one worker dispatches the remote operation

### Requirement: Remote synchronization failures remain actionable
The system SHALL retain bounded attempt metadata and safe failure classifications without storing credentials or unnecessary response bodies. A definitive remote rejection SHALL be recorded as failed and SHALL require explicit reconciliation before another remote write.

#### Scenario: Definitive remote rejection occurs
- **WHEN** the remote service rejects an operation with a definitive response
- **THEN** the system records a safe error code and does not automatically replay the write

#### Scenario: Diagnostics are inspected
- **WHEN** an authorized operator inspects a failed or unknown synchronization
- **THEN** diagnostics omit passwords, API secrets, and full sensitive payloads

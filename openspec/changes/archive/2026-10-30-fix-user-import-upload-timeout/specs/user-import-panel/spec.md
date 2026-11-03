## ADDED Requirements

### Requirement: Upload deadline and failure feedback
CSV upload requests SHALL use a dedicated five-minute client deadline rather than the shared five-second API timeout. The UI SHALL distinguish client timeout codes from HTTP server failures, preserve last known state, and never display raw server exception text.

#### Scenario: Slow upload response
- **WHEN** an otherwise valid upload response takes longer than five seconds but less than five minutes
- **THEN** the browser accepts the response and displays the queued import

#### Scenario: Timeout with uncertain outcome
- **WHEN** a client upload/request deadline expires
- **THEN** the operator is told to refresh recent imports before retrying

#### Scenario: Server failure
- **WHEN** the server returns an HTTP 5xx response
- **THEN** the operator sees the status and safe recovery instructions without response-body secrets

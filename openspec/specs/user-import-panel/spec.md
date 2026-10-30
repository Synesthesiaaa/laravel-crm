# User Import Panel

## Purpose

Allow Super Admins to preview and import large staff CSV files from User Access with protected private uploads, durable background processing, and clear recovery feedback.

## Requirements

### Requirement: Super Admin import workspace
The User Access panel SHALL allow a Super Admin to upload a local UTF-8 CSV, download its template, review validation totals, explicitly start valid account creation, see persistent progress, cancel unfinished work, and download safe row errors. Existing single-user management SHALL remain available.

#### Scenario: Preview then import
- **WHEN** an authorized operator uploads a valid CSV
- **THEN** a background preview reports eligible, skipped, and rejected rows without creating users
- **AND** only an explicit start action creates valid accounts using the existing importer rules

### Requirement: Persist bounded background batches
The system SHALL process at most 50 CSV records per job reservation on a dedicated asynchronous queue and atomically persist source cursor, counters, errors, identity hashes, and account writes. Retried or duplicated deliveries SHALL resume without recreating committed users; stale phases and terminal jobs SHALL do nothing.

#### Scenario: Browser closes or worker retries
- **WHEN** the browser closes or a worker retries after a committed batch
- **THEN** background work continues from its saved cursor and the operator can reload the resulting progress

### Requirement: Protect account imports and credentials
Every import endpoint SHALL enforce Super Admin access and import ownership. Workers SHALL recheck the submitting account's role. Source files SHALL be stored privately without download access, expire within 24 hours, and be deleted on terminal completion/cancellation/failure. Public payloads and reports SHALL contain no source secrets or raw exception data. Non-Agent roles SHALL require explicit opt-in.

#### Scenario: Unauthorized or different owner
- **WHEN** a non-Super-Admin or another operator requests an import artifact or action
- **THEN** access is denied and no import work is performed

#### Scenario: Cancel or expire
- **WHEN** an import is cancelled, fails, completes, or expires
- **THEN** its source credentials are removed and subsequent deliveries cannot create more users

### Requirement: Accessible persistent feedback
The panel SHALL match existing shared components, label controls and statuses, support responsive light/dark layouts, show upload/queue/progress/failure/completion states, and allow recovery from polling failures without losing server state.

#### Scenario: Connection interrupted
- **WHEN** status polling fails
- **THEN** the last known progress remains visible with a retry action and no duplicate start request is required

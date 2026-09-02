## ADDED Requirements

### Requirement: Local telephony history persistence

The system SHALL persist normalized VICIdial historical call rows in a local telephony call-history table separate from CRM form-submission history and live call-session state.

#### Scenario: A source row is stored
- **WHEN** synchronization receives a valid normalized VICIdial call
- **THEN** the local record stores its server, CRM campaign scope, source table, stable VICIdial identifier, campaign/list/lead identifiers, user, phone, status, timestamps, direction, duration, supported wait time, source update time, and sync time

#### Scenario: Local schema supports normal reads
- **WHEN** a Call History query filters by CRM campaign and call time
- **THEN** the schema provides an index that supports the scoped time-ordered query
- **AND** separate useful indexes support agent, phone, status, disposition, and source identity lookups

### Requirement: Idempotent source identity and upsert

The system SHALL use the VICIdial server, source table, source unique call identifier, and CRM campaign scope as a stable local identity and SHALL upsert synchronized rows instead of blindly inserting duplicates.

#### Scenario: The same call is synchronized twice
- **WHEN** the same source call is returned in an initial fetch and an overlap fetch
- **THEN** the second synchronization updates the existing local row or makes no material change
- **AND** the local row count does not increase

#### Scenario: A source row has no authoritative identifier
- **WHEN** a source row lacks its authoritative VICIdial unique identifier
- **THEN** the synchronizer uses the documented deterministic fallback only when the required source fields are present
- **AND** it does not use phone number alone or agent-plus-phone alone as identity

### Requirement: Incremental synchronization checkpoints

The system SHALL maintain a per-server, per-CRM-campaign synchronization checkpoint and SHALL fetch bounded ranges after the checkpoint with a configurable overlap window.

#### Scenario: Recent sync advances after success
- **WHEN** a recent synchronization fetches and upserts all rows in its window successfully
- **THEN** the sync state records the latest successful source timestamp/identifier and completion time

#### Scenario: Overlap protects late-arriving rows
- **WHEN** the previous checkpoint is 10:00 and the configured overlap is five minutes
- **THEN** the next fetch begins no later than 09:55
- **AND** duplicate rows in that overlap are safely upserted

#### Scenario: Failed sync preserves checkpoint
- **WHEN** a fetch, parse, or batch upsert fails
- **THEN** the last successful checkpoint is not advanced
- **AND** the failure classification, time, and safe diagnostic metadata are recorded

### Requirement: Background recent and historical synchronization

The system SHALL perform VICIdial synchronization in queue jobs and SHALL provide separate recent synchronization and chunked historical backfill paths.

#### Scenario: Scheduled recent synchronization
- **WHEN** the application scheduler reaches the configured recent-sync cadence
- **THEN** it dispatches synchronization jobs for eligible mapped server/campaign scopes
- **AND** the scheduler request does not perform the remote fetch itself

#### Scenario: Administrative backfill
- **WHEN** an authorized operator runs the call-history backfill command with server, campaign, and date-range options
- **THEN** the requested range is divided into bounded chunks and queued or processed through the same synchronization service
- **AND** the command does not require a single unbounded historical download

#### Scenario: Normal users do not wait for backfill
- **WHEN** a user opens Call History while a backfill is pending
- **THEN** the page renders immediately and displays available local data plus sync status

### Requirement: Concurrent synchronization protection

The system SHALL prevent duplicate synchronization jobs for the same server and CRM campaign scope from running concurrently.

#### Scenario: Multiple refresh requests target one scope
- **WHEN** several users request refresh for the same scope at nearly the same time
- **THEN** at most one equivalent job is queued or running
- **AND** other requests receive an in-progress status without issuing additional VICIdial fetches

#### Scenario: Different scopes synchronize independently
- **WHEN** two CRM campaigns map to different synchronization scopes
- **THEN** their jobs may proceed independently while retaining server/campaign isolation

### Requirement: Retry, timeout, and failure recovery

The system SHALL apply bounded timeouts, limited retries with backoff, and failure classification to background VICIdial synchronization without retrying invalid configuration indefinitely.

#### Scenario: Temporary remote failure
- **WHEN** a synchronization encounters a timeout or temporary network failure
- **THEN** the job retries within the configured limit using backoff
- **AND** existing local calls and the previous checkpoint remain available

#### Scenario: Invalid credentials or schema failure
- **WHEN** synchronization encounters an authentication, permission, or unsupported-schema failure
- **THEN** the job records the classification and stops retrying according to the configured policy
- **AND** it does not erase local history

#### Scenario: Remote request is bounded
- **WHEN** a VICIdial request exceeds its configured timeout
- **THEN** the job fails or retries within a bounded worker duration
- **AND** it does not hang indefinitely

### Requirement: Sync health and performance diagnostics

The system SHALL expose safe synchronization health for each scope, including status, last successful sync, last failure, duration, inserted/updated counts, and current backlog or in-progress state when available.

#### Scenario: Healthy scope reports freshness
- **WHEN** a scope has completed a successful synchronization
- **THEN** Call History status includes the last successful sync time and a healthy/up-to-date or delayed freshness state

#### Scenario: Sync fails after prior success
- **WHEN** a new synchronization fails after an earlier successful run
- **THEN** status reports delayed/unavailable synchronization and the prior successful time
- **AND** existing local rows remain readable

#### Scenario: Diagnostics are sanitized
- **WHEN** synchronization writes structured logs or returns health metadata
- **THEN** it includes safe scope and timing information
- **AND** it excludes credentials and full unmasked phone numbers

### Requirement: Shared layout is independent of remote synchronization

The system SHALL prevent historical synchronization and unnecessary live VICIdial polling from blocking shared layout, sidebar, topbar, and navigation rendering.

#### Scenario: Shared layout initializes
- **WHEN** an authenticated user renders any CRM page
- **THEN** shared layout initialization uses local session state or deferred agent-specific operations
- **AND** it does not synchronously fetch historical VICIdial data

#### Scenario: VICIdial is unavailable
- **WHEN** VICIdial is unreachable during background synchronization or deferred operational polling
- **THEN** shared navigation still renders and remains usable
- **AND** the failure is isolated to the relevant telephony status or sync indicator

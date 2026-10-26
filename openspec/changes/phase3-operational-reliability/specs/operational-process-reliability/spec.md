## ADDED Requirements

### Requirement: Artisan command signatures are unique
The system SHALL expose distinct command signatures for separate AMI listener implementations and SHALL document the intended command for each process profile.

#### Scenario: Commands are registered
- **WHEN** Laravel discovers the AMI listener commands
- **THEN** each implementation has a distinct signature and can be invoked independently

### Requirement: Runtime startup fails before serving when migrations fail
The documented local startup profiles SHALL complete required migrations before starting web and worker processes and SHALL propagate migration failures.

#### Scenario: Migration command fails
- **WHEN** a startup migration fails
- **THEN** the startup process exits with a failure and does not start web or queue workers

#### Scenario: All configured operational queues are enabled
- **WHEN** the documented worker profile starts
- **THEN** it consumes each default-enabled queue lane required by local application features
- **AND** email campaign workers remain an explicit opt-in in local startup profiles

### Requirement: CI enforces configured quality gates
The CI workflow SHALL fail when required coverage cannot be generated or falls below its configured threshold, and SHALL run JavaScript tests and production asset compilation.

#### Scenario: Coverage generation fails
- **WHEN** the coverage command fails or produces no report
- **THEN** the workflow fails without falling back to an ungated test run

#### Scenario: Frontend verification runs
- **WHEN** the CI workflow evaluates a change
- **THEN** it installs locked frontend dependencies, runs the JavaScript test command, and builds production assets

### Requirement: Concurrent database behavior is tested on MySQL
The project SHALL include a contention test using independent connections against an isolated CI MySQL service for the state protected by row locks.

#### Scenario: Concurrent requests contend for one agent reservation
- **WHEN** two independent transactions attempt to reserve the same agent concurrently
- **THEN** persisted state permits at most one active reservation

### Requirement: Authorization uses the supported role authority consistently
Role middleware and telephony feature gating SHALL consult the same `users.role` authority written by supported user administration, without rewriting unverified legacy role data.

#### Scenario: A supported administrator assigns a role
- **WHEN** an administrator changes a user's role through the supported user-management path
- **THEN** general role middleware and telephony feature gating evaluate the same stored role

### Requirement: Client and throttle telemetry is bounded and sanitized
The system SHALL record actionable client-error and throttle event categories without logging raw client messages, stack traces, request bodies, credentials, or customer data.

#### Scenario: Client reports a browser error
- **WHEN** a valid client-error event is submitted
- **THEN** the application records a bounded safe category and returns the documented acknowledgement

#### Scenario: Client reports sensitive values
- **WHEN** a client-error event includes message, stack, request data, or credential-like values
- **THEN** the application discards those values and logs no sensitive payload

#### Scenario: A request is throttled
- **WHEN** a request exceeds its configured rate limit
- **THEN** the throttle is logged once at the exception boundary with safe bounded context and the response remains HTTP 429

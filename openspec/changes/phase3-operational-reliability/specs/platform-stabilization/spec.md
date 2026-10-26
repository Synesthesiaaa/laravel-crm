## ADDED Requirements

### Requirement: Failure telemetry is captured at observable boundaries
The system SHALL capture client-reported errors and throttling failures at the route or exception boundary where the failure can be observed.

#### Scenario: Throttle middleware throws
- **WHEN** Laravel's throttle middleware throws before downstream middleware executes
- **THEN** the exception boundary records one sanitized throttle event

#### Scenario: Client-error endpoint receives a valid event
- **WHEN** an authenticated or public client submits a schema-valid bounded error category
- **THEN** the endpoint records safe diagnostic metadata and acknowledges receipt

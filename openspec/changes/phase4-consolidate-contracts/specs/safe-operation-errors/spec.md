## ADDED Requirements

### Requirement: Unexpected persistence failures use safe reference messages
The system SHALL return a stable user-facing failure message with a unique reference identifier for unexpected persistence failures. Internal logs SHALL include that reference and the exception class, and SHALL NOT include raw exception messages, credentials, submitted customer values, or full request payloads.

#### Scenario: Form persistence fails unexpectedly
- **WHEN** form persistence encounters an infrastructure exception
- **THEN** the response contains a safe message and reference identifier while the internal log records the same reference and exception class without the exception message

#### Scenario: Call history or disposition persistence fails
- **WHEN** call history or disposition persistence encounters an unexpected exception
- **THEN** the operation returns a safe failure with a reference identifier and no raw exception details reach the response or log

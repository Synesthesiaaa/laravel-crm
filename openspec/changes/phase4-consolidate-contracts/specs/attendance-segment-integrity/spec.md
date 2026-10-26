## ADDED Requirements

### Requirement: Attendance segment transitions are serialized per user
Starting, ending, and logout-closing a custom attendance segment SHALL lock a stable per-user database row inside the transaction that reads the current state and writes the event. At most one custom segment SHALL be considered open for a user.

#### Scenario: Concurrent start requests
- **WHEN** two requests start custom statuses for the same user concurrently on a database with row-lock support
- **THEN** one start is recorded and the other receives the existing active-status validation error

#### Scenario: Concurrent end requests
- **WHEN** two requests end the same open status concurrently
- **THEN** one end event is recorded and the second receives the no-active-status validation error

### Requirement: Open attendance segments span calendar days
The system SHALL determine open custom attendance state across calendar-day boundaries by comparing the latest event for each status type, rather than restricting state reconstruction to today's events.

#### Scenario: Overnight status is ended after midnight
- **WHEN** a status starts before midnight and no matching end is recorded before the next day
- **THEN** the next day's end request resolves and closes that open status

#### Scenario: Closed status does not remain open
- **WHEN** the latest event for a status type is an end event
- **THEN** that status type is not returned as open

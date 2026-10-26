## ADDED Requirements

### Requirement: Current call durations are non-negative
The system SHALL report elapsed call duration as a non-negative number of seconds from the call's answered timestamp through the current time.

#### Scenario: Answered call is in the past
- **WHEN** a call has an `answered_at` timestamp earlier than now
- **THEN** the reported elapsed duration is non-negative and reflects elapsed time

#### Scenario: Answer timestamp is in the future
- **WHEN** a timestamp is ahead of the current clock due to skew
- **THEN** the reported elapsed duration is zero rather than negative

### Requirement: Supervisor state uses the newest session per agent
The system SHALL select the newest eligible VICIdial session for each agent when building an operational snapshot.

#### Scenario: Duplicate sessions exist for an agent
- **WHEN** multiple eligible sessions are returned for one agent
- **THEN** the snapshot uses the session with the newest synchronization time and stable tie-break ordering

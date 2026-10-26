## ADDED Requirements

### Requirement: Ambiguous outbound dial outcomes remain reserved
The system SHALL preserve an explicit unknown outcome and the agent's active-call reservation when an outbound dial may have reached VICIdial but no definitive response was received.

#### Scenario: Dial request times out after dispatch
- **WHEN** `external_dial` times out or returns an ambiguous transport failure
- **THEN** the call session remains active with an unknown remote outcome and the agent cannot initiate a duplicate call
- **AND** the dial response and status endpoint identify the reserved session and unknown outcome
- **AND** the agent interface retains the reservation and warns the agent to verify before retrying

#### Scenario: VICIdial definitively rejects a dial
- **WHEN** VICIdial returns a definitive rejection for `external_dial`
- **THEN** the call session transitions to failed and the reservation is released

#### Scenario: Authoritative callback resolves the dial
- **WHEN** an authoritative callback correlated to the same session arrives
- **THEN** the session transitions according to the callback and the unknown outcome marker is resolved

### Requirement: AMI reconciliation acknowledges only applied transitions
The system SHALL mark an unmatched AMI event processed only after its correlated call transition succeeds or is confirmed as already applied.

#### Scenario: State transition is rejected
- **WHEN** reconciliation cannot apply the event to the call's persisted state
- **THEN** the event remains unprocessed with bounded retry metadata

#### Scenario: Transition is already applied
- **WHEN** reconciliation confirms the same event's intended state is already persisted
- **THEN** the event may be marked processed without applying a duplicate transition

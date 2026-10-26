## ADDED Requirements

### Requirement: Call transitions use persisted state

The system SHALL reload and lock a call session inside the state-transition transaction, validate the requested transition against the persisted status, and SHALL NOT overwrite a terminal state using a stale model instance. Hangup outcome selection SHALL use the same persisted status as the write.

#### Scenario: Stale ring event follows terminal completion
- **WHEN** a caller holds a stale `ringing` model while another operation commits `completed`
- **THEN** a later transition using the stale model SHALL leave the persisted session `completed`
- **AND** no state-change event SHALL describe an uncommitted transition

#### Scenario: Duplicate transition is idempotent
- **WHEN** two callers request the same transition for one session
- **THEN** the persisted row SHALL contain that state once
- **AND** the second result SHALL be treated as already applied

### Requirement: Answer events establish a call through one domain operation

The system SHALL process authoritative AMI Bridge/DialEnd and correlated VICIdial answer events through one connected-call operation that accepts valid pre-answer states, sets `answered_at`, and persists `in_call` without requiring a missing intermediate event.

#### Scenario: Bridge arrives while ringing
- **WHEN** a correlated AMI Bridge event arrives for a `ringing` session
- **THEN** the session transitions to `in_call`
- **AND** `answered_at` is populated

#### Scenario: Answer arrives while dialing
- **WHEN** a correlated answer event arrives before a ring event
- **THEN** the session transitions to `in_call`
- **AND** a later stale ring event SHALL NOT move it backward

### Requirement: An agent can reserve only one outbound call at a time

Outbound call admission SHALL lock the agent record, check all persisted active sessions, and create a local call reservation in one transaction before issuing VICIdial `external_dial`. A second request for the same agent SHALL NOT issue another remote dial while an active reservation exists.

#### Scenario: Two outbound requests compete
- **WHEN** two outbound requests for one agent reach the reservation boundary concurrently
- **THEN** at most one call session is reserved
- **AND** VICIdial `external_dial` is issued at most once
- **AND** the other request receives the existing already-in-call result

#### Scenario: Existing older active session exists
- **WHEN** more than one non-terminal session already exists for an agent
- **THEN** a new outbound request SHALL be blocked until every active session is resolved

### Requirement: VICIdial callbacks cannot select another call

VICIdial Call URL and Agent Push Events SHALL match call sessions by exact available call identity and validate available campaign, agent, and lead context. Unknown call IDs SHALL NOT fall back to a latest session. A callback without sufficient identity SHALL leave call state unchanged and SHALL be reported as unmatched or ambiguous.

#### Scenario: Callback references an exact session
- **WHEN** a callback's call ID and available context match one stored call session
- **THEN** only that session is updated

#### Scenario: Unknown authoritative ID is received
- **WHEN** a disposition, dead-call, or event callback contains a call ID that does not match the supplied context
- **THEN** no other session is selected by campaign, lead, phone, or recency
- **AND** the callback is reported as unmatched

#### Scenario: Callback identity is absent or ambiguous
- **WHEN** the callback has no exact call ID and its available identity resolves to zero or multiple eligible sessions
- **THEN** the callback SHALL NOT change any call-session state

#### Scenario: Push events arrive out of order
- **WHEN** an older answer or hangup event arrives after a newer call session has become active
- **THEN** the event SHALL not transition the newer session unless exact call identity and context match it

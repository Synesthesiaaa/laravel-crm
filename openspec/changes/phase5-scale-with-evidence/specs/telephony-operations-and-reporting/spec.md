## MODIFIED Requirements

### Requirement: Large call-history sync is bounded and replay-safe

Historical call-history synchronization SHALL read and persist a bounded ordered page at a time. Pages SHALL use a stable non-null key order, and each page SHALL be persisted through the existing source-identity upsert before the next page is fetched. A failed page SHALL leave the completed-window checkpoint unchanged so a retry can safely replay prior pages.

#### Scenario: Large historical window
- **WHEN** a VICIdial history window contains more rows than the configured page size
- **THEN** the provider yields bounded ordered pages and the local store persists each page before reading the next

#### Scenario: A later sync page fails
- **WHEN** a remote page or local page upsert fails after earlier pages succeeded
- **THEN** the sync checkpoint does not advance and the next attempt replays rows without duplicate history records

### Requirement: Realtime report aggregation has bounded application memory

Local Today metrics SHALL use database aggregates, and local rolling metrics SHALL process a bounded number of call sessions at a time. The system SHALL preserve the existing campaign scope, event-window predicate, call counts, dispositions, averages, and non-negative duration behavior.

#### Scenario: Today report has many local calls
- **WHEN** the realtime report uses the CRM fallback over a large day of call sessions
- **THEN** it returns the same metrics without hydrating every call session into one application collection

#### Scenario: Rolling report spans many calls
- **WHEN** the rolling window matches more rows than the configured processing chunk
- **THEN** the report processes stable bounded chunks and returns the same aggregate values

### Requirement: Supervisor target identity stays separate from API authentication

Supervisor Agent API actions SHALL derive the target agent identifier from the selected CRM user record while retaining the authenticated supervisor's VICIdial credentials. Caller-supplied reserved `agent_user` keys SHALL remain rejected.

#### Scenario: Supervisor targets a selected agent
- **WHEN** an authorized supervisor requests a monitor, whisper, pause, or logout action for a CRM user
- **THEN** the transport authenticates with the supervisor's VICIdial identity and sends the selected user's VICIdial identity only as the action target
- **AND** an untrusted reserved `agent_user` override is rejected before an HTTP request

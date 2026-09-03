## ADDED Requirements

### Requirement: Call History synchronization jobs are consumable

The system SHALL configure its Horizon and local worker entry points to consume the `telephony` queue used by `SyncVicidialCallHistoryJob`. Scheduled and refresh-dispatched jobs SHALL remain asynchronous and SHALL NOT execute remote synchronization during Call History page rendering or local read requests.

#### Scenario: Scheduled job reaches a worker
- **WHEN** the scheduler dispatches a recent Call History synchronization job
- **THEN** the job is placed on the `telephony` queue
- **AND** the configured worker topology consumes that queue
- **AND** the scheduler does not perform the VICIdial fetch itself

#### Scenario: Local worker matches production routing
- **WHEN** a developer starts the documented queue worker
- **THEN** it listens to both `telephony` and `default` queues
- **AND** Call History jobs do not remain pending solely because of an omitted queue name

### Requirement: Invalid synchronization cursors recover safely

The system SHALL detect a per-scope last-call checkpoint later than the current synchronization end, SHALL record a sanitized anomaly, and SHALL use a bounded configured recent window instead of issuing a future-to-now query. It SHALL preserve the prior checkpoint until a synchronization succeeds.

#### Scenario: Future checkpoint falls back to recent window
- **WHEN** `last_call_at` is later than the current synchronization end
- **THEN** the provider receives a recent bounded window ending at the current time
- **AND** the previous checkpoint is not advanced or erased before success

### Requirement: Synchronization health exposes actionable safe diagnostics

The system SHALL expose last attempt time, last successful time, current window, duration, row counters, mapped campaign count, and failure classification for an authorized scope without credentials or full phone numbers.

#### Scenario: Operator inspects a failed scope
- **WHEN** a sync attempt fails after a prior successful run
- **THEN** health reports the failure classification, last attempt, last successful time, current safe counters, and preserved local-data availability

#### Scenario: Successful retry recovers health
- **WHEN** a subsequent queued synchronization succeeds
- **THEN** the sync state becomes `healthy`
- **AND** the last successful time and counters are updated
- **AND** the previous failure classification and message are cleared

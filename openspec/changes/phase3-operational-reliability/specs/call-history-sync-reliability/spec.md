## ADDED Requirements

### Requirement: Local worker profiles consume enabled queue lanes
Documented local worker profiles SHALL consume every configured queue lane required by enabled call-history and telephony synchronization features.

#### Scenario: A synchronization job is dispatched
- **WHEN** the local application dispatches a call-history or telephony synchronization job
- **THEN** at least one documented, running worker profile consumes the job's configured connection and queue

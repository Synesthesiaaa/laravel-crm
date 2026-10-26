## MODIFIED Requirements

### Requirement: Frozen campaign delivery verification is recipient-bounded

Newly frozen campaigns SHALL retain the full canonical manifest approval hash and authenticate a compact immutable campaign header and each recipient snapshot independently. Delivery verification SHALL validate only the campaign header and the selected recipient payload and assigned documents. Previously frozen campaigns SHALL retain their existing full-manifest verification path until completion.

#### Scenario: New campaign sends one recipient
- **WHEN** a version-2 frozen campaign sends one recipient from a large recipient set
- **THEN** the delivery verifies that recipient and its assigned documents without hashing or scanning every campaign recipient

#### Scenario: A frozen header or recipient snapshot changes
- **WHEN** the stored campaign header, recipient snapshot, or assigned document metadata no longer matches its authenticated value
- **THEN** the recipient is blocked before entering the transport boundary

#### Scenario: Previously queued campaign uses version 1
- **WHEN** an already-frozen version-1 campaign is resumed after deployment
- **THEN** it continues through the existing full-manifest verification behavior

### Requirement: Campaign totals stay current without per-recipient recounts

Recipient status transitions SHALL atomically maintain campaign sent, failed, skipped, open, and attention counters. A scheduled reconciliation SHALL repair derived-counter drift by campaign. Individual recipient delivery SHALL NOT group-count all recipients after every send.

#### Scenario: Recipient reaches a terminal delivery outcome
- **WHEN** a recipient is accepted, skipped, blocked, or marked uncertain
- **THEN** the matching campaign counters change once in the same transaction as the recipient status

#### Scenario: An active campaign's counters drift
- **WHEN** periodic reconciliation inspects an active campaign
- **THEN** it recomputes status totals once and repairs the stored counters and final campaign state without resending any message

#### Scenario: An existing active campaign has no derived counters
- **WHEN** a legacy campaign is first transitioned or reconciled after deployment
- **THEN** the counters are initialized from existing recipient statuses without a migration backfill

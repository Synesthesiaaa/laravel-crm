## Context

VICIdial mutations are sent through HTTP and AMI, while the CRM persists call, disposition, and Agent Capture state locally. A lost response can leave the remote side changed while the CRM assumes failure. The current deferred jobs do not preserve intent durably, and reconciliation has more than one implementation. The design must preserve the existing VICIdial API contracts and never call real telephony during validation.

## Goals / Non-Goals

**Goals:** Prevent blind replay of non-idempotent writes; keep uncertain dials visible and reserved; persist remote synchronization intent atomically with local changes; centralize reconciliation and acknowledge AMI events only after successful transitions; fix duration and duplicate-session selection.

**Non-Goals:** Change VICIdial/Asterisk protocols, replay historical data, perform production migrations, automatically replay uncertain writes, or redesign agent workflows.

## Decisions

- Classify operations by retry safety at the transport boundary. Read-only calls may use bounded retries; write calls such as `add_lead`, `update_lead`, and `external_dial` do not retry after ambiguous transport failure. Return a safe outcome classification without response credentials or sensitive request values.
- For uncertain `external_dial`, persist an outcome marker on the reserved `CallSession`, keep the agent reservation active, and expose an explicit unknown state to the agent status and reconciliation paths. Only authoritative callbacks resolve it; a definitive VICIdial rejection remains a failure.
- Add an encrypted `telephony_sync_outbox` record in the same DB transaction as each local disposition or lead-field write. Store only the minimum payload needed for the remote request. Use unique operation keys, attempt metadata, and pending/processing/synced/failed/unknown states. Redispatch may recover work that has not been claimed; after a remote write starts, transport ambiguity or a worker crash becomes unknown and is never automatically replayed.
- Process outbox records through a durable database-queue worker and scheduled dispatcher. Claim rows with a DB transaction and row lock, release the transaction before remote I/O, and make duplicate queue delivery a no-op after the first claim. Failed local claim/commit must not lose the outbox row.
- Make `TelephonyReconciliationService` the sole state reconciliation path. An AMI event is marked processed only after `CallStateService` confirms a valid transition or a safe idempotent already-applied result; retain bounded attempt metadata for failures.
- Correct elapsed-time direction and select the latest session per agent after deduplication. Keep response fields and ordering otherwise stable.

## Risks / Trade-offs

- [Remote write outcome can remain unknown] → Require explicit reconciliation before replay and keep the local state visible.
- [Outbox payload could contain customer data] → Encrypt payloads at rest with Laravel encryption, minimize fields, bound retention, and never include credentials.
- [Database driver locking differs] → Use portable transactions and test actual contention against the isolated CI MySQL service; do not claim SQLite proves contention behavior.
- [New migration is required] → Make it additive and reversible; validate schema only against isolated test databases.
- [Unknown calls can occupy an agent slot longer] → This reflects uncertainty accurately; show the unknown outcome to the agent and wait for authoritative callback evidence rather than freeing the slot unsafely.

## Migration Plan

1. Apply the additive outbox and call-outcome/reconciliation metadata migrations through the normal deployment migration process.
2. Deploy the application with the `database` connection available for `telephony-sync` jobs.
3. Start the dedicated worker and scheduler dispatcher together; pending work remains persisted if a worker is unavailable.
4. Roll back application code only after stopping dispatch; leave additive records intact for forward recovery. Never run schema rollback against production as part of this change.

## Open Questions

- Which exact authoritative VICIdial callback is available for resolving uncertain outbound command outcomes? Keep unresolved sessions unknown if no callback proves the result.

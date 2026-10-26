## 1. Transport and call outcomes

- [x] 1.1 Add focused tests proving write operations are not retried while read-only operations retain bounded retries.
- [x] 1.2 Implement safe operation retry classification and unknown transport outcome reporting.
- [x] 1.3 Persist ambiguous outbound dial outcomes, return the reserved session identity, expose the state to agents, and preserve reservations until authoritative resolution.
- [x] 1.4 Add tests for ambiguous timeout, callback resolution, duplicate-call prevention, and status/UI recovery. Definitive rejection remains covered by existing call-flow tests.

## 2. Durable synchronization

- [x] 2.1 Add additive, reversible migration and model for encrypted telephony synchronization outbox records.
- [x] 2.2 Persist disposition and Agent Capture sync intents atomically with their local records.
- [x] 2.3 Implement outbox claiming, durable dispatch, pre-claim redispatch, unknown-outcome handling, and safe diagnostics.
- [x] 2.4 Add isolated tests for transaction rollback, duplicate dispatch prevention, worker-loss handling, and secret redaction.

## 3. Reconciliation and operational correctness

- [x] 3.1 Consolidate reconciliation behavior into `TelephonyReconciliationService` and delegate the scheduled job.
- [x] 3.2 Retain AMI retry metadata and mark events processed only after valid or already-applied transitions.
- [x] 3.3 Correct call duration calculation and newest-session selection.
- [x] 3.4 Add focused tests for failed transitions, duration boundaries, and duplicate sessions.

## 4. Phase verification and sync

- [x] 4.1 Run focused PHP tests and formatting checks without contacting telephony services.
- [x] 4.2 Review additive migration safety, operation logs, queue routing, and changed files.
- [x] 4.3 Strictly validate after final verification, then archive this change.

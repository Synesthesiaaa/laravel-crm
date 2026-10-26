## Context

The CRM maintains its own `CallSession` state while VICIdial and Asterisk provide asynchronous call events. The current transition service trusts the caller's possibly stale Eloquent model, the outbound path creates a session after checking for active calls outside a shared lock, and several webhook matchers fall back to a user's newest call. CRM forms also store configurable visibility rules, but server validation does not evaluate them; CSV imports call the submission service row by row and have no replay receipt. The dedicated Agent Capture entry imports the component code but not Alpine, while the shared layout uses both Alpine's automatic `init()` hook and explicit `x-init="init()"` on several components.

The audit findings T1–T4, C1–C3, and F1–F2 are independently confirmed in the current implementation. Phase 2 must preserve the existing Laravel, Blade, Alpine, VICIdial, and dynamic-form boundaries while ensuring that failed or ambiguous operations cannot attach to a different call or duplicate an imported row.

## Goals / Non-Goals

**Goals:**

- Make state transitions validate and update the persisted session under a row lock; determine hangup outcomes from that locked state.
- Let authoritative bridge/answer events move a call to `in_call` through one valid state operation.
- Serialize outbound call admission per agent, then invoke VICIdial only after a local session reservation commits.
- Match callbacks using exact call identity plus available campaign/agent/lead context; leave state unchanged when an event cannot be safely correlated.
- Evaluate the configured visibility rule on the server for both standard form submissions and Agent Capture; never trust `visible_fields` for required-field decisions.
- Preserve accepted base-10 decimal strings and reject values that exceed the existing decimal storage contract rather than silently stripping, truncating, or rounding them.
- Make a CSV row submission and its import receipt atomic so replay skips rows that already committed.
- Initialize standalone Alpine once and rely on its component lifecycle once per tree initialization.
- Verify with isolated fixtures, fake telephony boundaries, focused tests, and browser checks.

**Non-Goals:**

- No live VICIdial, Asterisk, PSTN, SMS, email, or queue side effects during validation.
- No rewriting or deleting existing customer/form data and no destructive schema operation.
- No attempt to infer event order from VICIdial's `counter`; the shipped documentation exposes that variable and warns events may arrive out of order, but does not define it as a monotonic call sequence.
- No redesign of call reconciliation/outbox behavior, attendance, Data Master, export security, or draft retention; those remain in later phases.
- No UI redesign or change to field labels, layouts, permissions, callback URLs, or established APIs.

## Decisions

### 1. Validate transitions against a locked database row

`CallStateService` will open a transaction, reload the `CallSession` with `lockForUpdate()`, and evaluate idempotency, terminal-state protection, and transition validity against that persisted row. `recordHangup()` will select failed versus completed from the same locked status rather than a stale pre-read. Existing `CallStateChanged` already implements Laravel's after-commit contract, so broadcasts remain tied to committed state.

For authoritative AMI Bridge/DialEnd and VICIdial answer events, a shared connected-call operation will allow a direct `dialing` or `ringing` to `in_call` transition while setting `answered_at`. This accounts for asynchronous integrations that may omit intermediate events without synthesizing a separate committed state.

### 2. Reserve outbound calls under the agent row lock

The outbound path will lock the local user row, query all active sessions for that user, and create the reservation in the same transaction. A second request waits for the first reservation and then returns the existing already-in-call error. The external `external_dial` request remains outside the transaction so database locks are not held during network I/O. Any existing active call blocks a new reservation; stale cleanup remains an explicit service responsibility.

An application-level lock was chosen over adding a generated-column uniqueness constraint. The latter is database-specific and may fail deployment if historical users already have multiple active rows; current evidence does not justify a data cleanup or schema constraint.

### 3. Stop callback fallback when identity is authoritative or ambiguous

When a VICIdial Call URL supplies `call_id`, lookup will be scoped by that ID and checked against available campaign, agent, and lead identity. Unknown IDs on disposition/dead callbacks will not fall back to a latest session. A start-call callback may create a new session only when its call ID is not already present and its own user/campaign/lead/phone context is valid. Without a call ID, fallback lookup is allowed only when the supplied identity resolves to exactly one eligible session.

Agent Push Events will use an explicit `call_id` field when present and will also recognize the documented `call_answered` message only when it exactly matches a stored VICIdial call ID. Call-state events without safe identity may still be logged/broadcast, but will not mutate a session. The VICIdial `counter` remains diagnostic metadata; ordering is not inferred from undocumented semantics.

This approach preserves known event contracts and leaves ambiguous remote state for reconciliation. It is safer than selecting the latest call, which can complete or answer a newer call after asynchronous delivery.

### 4. Derive conditional required fields on the server

A small shared PHP evaluator will implement the existing frontend operators (`equals`, `not_equals`, `in`, `not_in`) over scalar and array values. `FormSubmissionRequest`, `FormSubmissionService`, and `AgentCaptureController` will use the same evaluator. Hidden fields will not be required or persisted, and `visible_fields` remains accepted for compatibility but is not trusted as validation authority.

### 5. Keep numeric text within the existing storage contract

The dynamic form table stores ordinary numeric fields as `DECIMAL(65,2)` and identifier-like fields as strings. Numeric amount values will accept a base-10 decimal string with at most 63 integer digits and two fractional digits; signs are retained, exponent notation and excess precision are rejected. Identifier-like number fields will remain strings and preserve leading zeroes. No existing values are migrated.

The rules match the already-configured schema scale. Changing the schema precision or adding silent rounding would alter business meaning and is not justified by the audit evidence.

### 6. Add a row receipt keyed by import and CSV offset

An additive `lead_import_rows` table will store `lead_import_id`, stable CSV record number, generated request ID, status, and the target form record ID. A unique index on `(lead_import_id, row_number)` provides replay identity. The form row, call-history record, and receipt completion will commit in one database transaction. Validation failures are recorded once; infrastructure exceptions roll back the row transaction so Laravel can retry. Previously completed/failed rows are skipped on retry, and final import counts are recomputed from receipts.

Form storage preparation occurs before the per-row transaction to avoid DDL inside the receipt transaction. `FormSubmitted` will dispatch after commit so import listeners cannot observe an uncommitted row. Existing uploads always create a `LeadImport`; jobs missing that parent will fail safely before importing.

Deployment must drain the imports queue before rollout because jobs already partway through the old non-idempotent implementation have no receipts to identify committed rows. This plan does not backfill or replay past imports.

### 7. Use Alpine's single lifecycle path

The standalone capture entry will import Alpine, publish it on `window`, load the existing form/component modules, and start Alpine once. Capture initial values will be passed from the component's boot object to `init()` without an explicit `x-init`. Other `x-init="init()"` attributes on Alpine components will be removed because Alpine invokes a component's `init()` automatically; existing `destroy()` methods will remain responsible for timers, polling, charts, and subscriptions during soft navigation.

## Risks / Trade-offs

- **Database lock behavior differs by engine** → Validate logic on isolated SQLite and use a dedicated MySQL concurrency test when a non-production MySQL test service is available; do not claim SQLite proves row-lock concurrency.
- **Callback installations may omit stable call IDs** → Keep status-changing callbacks fail-closed when correlation is ambiguous; rely on existing AMI/Call URL and reconciliation paths, and document the exact unmatched event in safe telemetry.
- **Existing field values may contain more than two decimal places** → Do not rewrite historical rows; new submissions beyond current schema scale receive a validation error.
- **Old queued imports lack receipts** → Drain the imports queue before deployment and do not retry pre-deployment processing jobs until their committed row counts are reconciled.
- **Standalone Alpine startup may expose another missing runtime dependency** → Validate the rendered webform with a local isolated Laravel server and no telephony connectors.

## Migration Plan

1. Add the new import-row table and model through an additive migration. Do not run migrations against the configured production database during development validation.
2. Deploy application code after pausing and draining existing `imports` queue workers; retain the original CSV files and `LeadImport` records.
3. Resume the `imports` queue with the new idempotent job code. Existing completed imports remain unchanged; newly processed imports create per-row receipts.
4. If rollback is required, stop new imports and roll back application code. Keep `lead_import_rows` and all submitted records; do not drop or delete the table as part of rollback.

## Open Questions

- None blocking implementation. The installed VICIdial version should still be checked during deployment to confirm how it places `call_id` in Agent Push Events; code will accept an explicit field and an exact message match, and will not use `counter` as an ordering guarantee.

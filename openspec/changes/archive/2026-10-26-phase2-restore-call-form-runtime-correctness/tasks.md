## 1. Atomic call state and outbound reservation

- [x] 1.1 Refactor `CallStateService` to reload and lock persisted sessions, validate transitions from the locked status, and choose hangup outcomes atomically.
- [x] 1.2 Add one connected-call transition for authoritative Bridge/DialEnd and correlated VICIdial answer events; cover dialing/ringing answers and backward stale events.
- [x] 1.3 Add state-service tests for stale model instances, terminal-state protection, hangup races, event dispatch, and valid answer paths.
- [x] 1.4 Lock the agent row and recheck all active sessions inside outbound call reservation; keep the VICIdial HTTP command outside the transaction.
- [x] 1.5 Add orchestration tests proving a second competing request cannot issue another mocked `external_dial` and cannot create a second reservation.

## 2. Callback correlation

- [x] 2.1 Scope VICIdial Call URL matching by exact call ID and validate available campaign, user, lead, and phone context; prevent fallback from unknown authoritative IDs.
- [x] 2.2 Require unique fallback identity where call ID is absent and leave unmatched/ambiguous session state unchanged.
- [x] 2.3 Pass Agent Push Events call identity and lead context into answer/end handlers; ignore `counter` for ordering and prevent latest-session mutation without a safe match.
- [x] 2.4 Add callback tests for exact matches, mismatched context, unknown IDs, duplicate events, and older events arriving after a newer call begins.

## 3. Conditional validation and numeric fidelity

- [x] 3.1 Add a shared server-side visibility evaluator matching existing scalar and array operators.
- [x] 3.2 Apply visibility-aware required/nullable rules in `FormSubmissionRequest` and skip hidden fields in `FormSubmissionService` persistence.
- [x] 3.3 Recompute Agent Capture visibility from configured rules; ignore client `visible_fields` as a validation source and exclude hidden values from storage.
- [x] 3.4 Add form-number validation for the existing decimal precision, preserving signs and identifier leading zeroes while rejecting exponent and excess precision values.
- [x] 3.5 Add request, service, and Agent Capture feature tests for visible/hidden requirements, forged visibility lists, negative decimals, excess scale, exponent input, and leading-zero identifiers.

## 4. Replay-safe CSV imports

- [x] 4.1 Add an additive `lead_import_rows` migration/model with a unique `(lead_import_id, row_number)` receipt key and no customer-value payload.
- [x] 4.2 Add an import preparation path that ensures form storage before per-row transactions and lets `FormSubmissionService` use a stable generated request ID for import rows.
- [x] 4.3 Make form row, call-history row, and import receipt commit together; dispatch form-submitted effects after commit and let infrastructure failures retry.
- [x] 4.4 Update the job to skip committed receipts, recompute counts from receipts, and fail safely when tracking data is unavailable.
- [x] 4.5 Add import tests for interruption after committed rows, retry without duplicates, deterministic row failure, rollback on infrastructure failure, and missing import tracking.

## 5. Alpine runtime and component lifecycle

- [x] 5.1 Start Alpine exactly once in the dedicated Agent Capture Vite entry and initialize the component from its boot values without explicit duplicate `x-init`.
- [x] 5.2 Remove redundant `x-init="init()"` from page and shared-widget components whose Alpine `init()` hook is automatic; verify existing `destroy()` cleanup.
- [x] 5.3 Add JavaScript/component coverage that detects one-time initialization and cleanup under reinitialization or soft-navigation teardown.
- [x] 5.4 Use Playwright on an isolated authenticated capture page to verify visible fields, prefill, conditional required behavior, submission feedback, and absence of console errors.

## 6. Phase verification and OpenSpec sync

- [x] 6.1 Run focused PHP tests for call state/orchestration/callbacks, form validation, Agent Capture, and import replay.
- [x] 6.2 Run relevant JavaScript tests, Pint, and `npm run build`; inspect the final diff and confirm no live telephony boundary was called.
- [x] 6.3 Validate an isolated browser flow and record actual browser/network evidence without using production records or external services.
- [x] 6.4 Update the phase specs and tasks to match implementation, run strict OpenSpec validation, then archive only after all required checks pass.

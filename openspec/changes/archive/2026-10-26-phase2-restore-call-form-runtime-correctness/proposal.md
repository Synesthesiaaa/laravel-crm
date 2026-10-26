## Why

The audit's Phase 2 findings are present in the current code: call transitions trust stale models and bypass valid answer transitions, outbound admission is not serialized, callback fallbacks can select another call, hidden required fields fail or can be bypassed, CSV retries replay committed rows, numeric values are rewritten, and the standalone capture page never starts Alpine. These defects can corrupt live call state or accepted CRM records, so they must be corrected before broader reliability and data-integrity work.

## What Changes

- **T1–T4:** Apply call state transitions against the persisted, locked session; centralize connected-call transitions; reserve outbound calls under a per-agent lock; and correlate VICIdial callbacks by call identity, campaign, and agent. Missing or ambiguous identities must not mutate an unrelated call.
- **C1:** Evaluate configured field visibility on the server for standard and Agent Capture submissions. Require only visible required fields and ignore client-supplied visibility lists for validation decisions.
- **C2:** Track each imported CSV row by import and stable row number, commit its submission and completion state atomically, and safely replay after worker interruption.
- **C3:** Preserve accepted decimal values, reject values the existing `DECIMAL(65,2)` storage cannot represent without silent rounding, and retain identifier strings such as leading-zero values.
- **F1–F2:** Start Alpine in the standalone Agent Capture bundle and remove duplicate `init()` invocations where Alpine already manages component initialization, preserving teardown during soft navigation.
- Add regression coverage for stale state, competing outbound requests, exact and ambiguous callback correlation, conditional validation, decimal fidelity, import interruption/replay, and frontend initialization.

## Capabilities

### New Capabilities

- `call-session-correctness`: Atomic state changes, one outbound reservation per agent, and safe correlation of telephony callbacks.
- `replay-safe-lead-imports`: Per-import row tracking and atomic replay behavior for queued CSV imports.
- `agent-capture-webform-runtime`: A working standalone Alpine runtime and server-authoritative conditional-field validation for Agent Capture.

### Modified Capabilities

- `crm-form-review-confirmation`: Server-side visibility-aware required validation and explicit numeric precision behavior for standard CRM forms.
- `production-ui-runtime`: Alpine component initialization and teardown occur once across initial loads and soft-navigation swaps.

## Impact

- Laravel call-state, outbound orchestration, VICIdial webhook, form-validation, import-job, model, and event code.
- One additive migration and model for CSV import row receipts; existing form and customer records are not rewritten.
- Vite entrypoint and Blade component lifecycle attributes; no design-system or framework replacement.
- Focused PHPUnit/JavaScript tests, Vite build, and isolated Playwright checks with stubbed VICIdial/Asterisk boundaries. Tests must not issue live calls or mutate the configured production database.
- Deployment must account for queued imports created before row receipts exist; the import queue must be drained before deploying this phase, or in-flight imports must be reviewed before retry.

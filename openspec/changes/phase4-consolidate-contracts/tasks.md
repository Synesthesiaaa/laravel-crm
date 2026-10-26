## 1. Form metadata and Data Master contracts

- [x] 1.1 Add tests for used-form identity protection and unused-form identifier corrections.
- [x] 1.2 Reject identity changes after field metadata or stored rows exist while preserving descriptive edits.
- [x] 1.3 Share dynamic field rules and restrict Data Master writes to active metadata-backed fields.
- [x] 1.4 Test provenance/unknown-column exclusion, field validation, and unchanged records after invalid input.

## 2. Attendance correctness

- [x] 2.1 Add state reconstruction tests for overnight segments and closed statuses.
- [x] 2.2 Move start/end/logout reads inside a transaction that locks the stable user row.
- [x] 2.3 Add isolated MySQL contention coverage for concurrent starts and ends and register it in CI.
- [ ] 2.4 Run attendance contention against the isolated MySQL CI database.

## 3. Dynamic form storage lifecycle

- [x] 3.1 Move additive table/column provisioning and nullability alignment into a dedicated service with a schema readiness check.
- [x] 3.2 Provision after form/field configuration changes and add an idempotent `forms:provision-storage` command.
- [x] 3.3 Remove schema DDL from submissions/imports and fail safely when readiness is stale.
- [x] 3.4 Provision explicitly in nullability tests and assert submissions emit no DDL.
- [x] 3.5 Document deployment provisioning and the conditional C10 preflight without changing or running the migration.

## 4. Safe exports and operation failures

- [x] 4.1 Add a shared CSV encoder and use it in extraction and capture-record exports.
- [x] 4.2 Test formula-like inputs and strict numeric preservation in both export paths.
- [x] 4.3 Replace raw exceptions in form, call-history, and disposition paths with safe references and sanitized logs.
- [x] 4.4 Test that exception text, credentials, and customer values do not reach responses or logs.

## 5. Browser runtime and draft privacy

- [x] 5.1 Guard preference storage and validate JSON before Alpine starts.
- [x] 5.2 Render the user-edit marker in markup and provide explicit unique IDs to repeated controls.
- [x] 5.3 Move Activity Log styles to a scoped Vite asset and select Echo's Reverb/Pusher driver explicitly.
- [x] 5.4 Add an additive draft-safe flag, expose its opt-in in Field Logic, and mark rendered controls.
- [x] 5.5 Filter drafts to opted-in fields, expire after eight hours, purge legacy/expired drafts, exclude phone values, and clear on logout.
- [x] 5.6 Add JavaScript and rendered-view coverage for blocked storage, IDs, transport selection, and draft retention.

## 6. Phase verification and synchronization

- [x] 6.1 Run focused PHP/JavaScript suites, formatting, asset build, and strict OpenSpec validation.
- [x] 6.2 Use Playwright with synthetic SQLite to sign in, open the dashboard and Call History, expand filters, submit a phone filter, and check for overflow at the browser's effective 1920px and 500px viewports. The service clamps a requested 375px viewport to 500px; isolated SIP/form configuration produced expected 422 responses.
- [x] 6.3 Synchronize root specifications and pass strict validation for all 44 specs.
- [ ] 6.4 Archive only after all required validation is complete.

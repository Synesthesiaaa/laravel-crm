## 1. Baseline and plan

- [x] 1.1 Inspect existing work, baseline tests, role boundaries and local consumer gap.
- [x] 1.2 Record secure design and acceptance requirements.

## 2. Ingestion and review

- [x] 2.1 Add additive schema, encrypted private document storage and bounded safe PDF/ZIP validation.
- [x] 2.2 Add strict manifest parsing, exact mapping, private previews and fail-closed tests.
- [x] 2.3 Add preflight, snapshots, dry-run and explicit confidential send confirmation.

## 3. Delivery and operations

- [x] 3.1 Integrate intended attachments, frozen composition, purpose separation and atomic claims.
- [x] 3.2 Add dedicated consumer heartbeat, pending recovery, uncertain handling and expiry cleanup.
- [x] 3.3 Verify one/two recipient fake deliveries, idempotent claims, cancellation and integrity regressions.

## 4. Interface and closeout

- [x] 4.1 Extend existing Blade form and mapping/status review with accessible responsive controls.
- [x] 4.2 Update operator/deployment documentation and policy configuration.
- [x] 4.3 Run relevant tests, migration rollback rehearsal, Pint, build and isolated browser flow.
- [ ] 4.4 Validate real approved Poppler/scanner on deployment, supervised worker and required organization policies; archive only after remaining operational/browser checks pass.

Specs synchronized to main capability. See validation.md for exact evidence and limits. Archive is intentionally deferred: positive real Poppler inspection is unavailable here; browser shell has existing Reverb/SIP configuration errors. Multi-process MySQL claim contention and real SMTP/provider outcomes were not exercised. No live messages were sent.

## 5. Synthetic SMTP test regression

- [x] 5.1 Reproduce the local/live-enabled guard failure before changing behavior.
- [x] 5.2 Allow configured synthetic self-tests independently of bulk sending and isolate SMTP/account errors from mapping errors.
- [x] 5.3 Verify local/disabled-bulk behavior, fixed account-only addressing, sanitized errors and unchanged mapping in tests and isolated browser; synchronize requirements.

## 6. Explicit local bulk sending

- [x] 6.1 Replace unconditional local prohibition with a shared live/local opt-in policy used by HTTP start, retry and delivery.
- [x] 6.2 Enable only the user-authorized local live/local flags, clear cached configuration and verify APP_ENV remains local without dispatching live campaigns.
- [x] 6.3 Verify opt-in, revocation, SMTP/worker/confirmation guards and testing transport restrictions in regression tests and isolated local browser/array-worker rehearsal; synchronize specs and runbook.

## 1. Design gate and baseline

- [x] 1.1 Obtain explicit approval of the canonical design, including initial provider adapter, Admin marketing scope and consent eligibility.
- [x] 1.2 Invoke Superpowers writing-plans after approval and create the implementation plan from these capability tasks.
- [x] 1.3 Record isolated email test baseline, current schema/route boundaries, safe browser fixture strategy and deployment limitations.
- [x] 1.4 Verify maintained compatible versions/licenses for self-hosted newsletter composition, server HTML sanitization and streaming XLSX reading before adding dependencies.

## 2. Additive persistence and authorization

- [x] 2.1 Add lifecycle/scheduling/import-readiness/dispatch/notification fields and new versioned composition fields with safe defaults and indexes.
- [x] 2.2 Add contact/segment/consent/import-run/suppression persistence with ownership and source references.
- [x] 2.3 Add dispatch intent, attempt, event receipt, token/link and notification persistence with unique idempotency keys.
- [x] 2.4 Rehearse additive migration and rollback on synthetic data; verify legacy records remain readable.
- [x] 2.5 Add policies/scoped queries/named routes/navigation for Admin-owned attachment-free marketing and unchanged Super Admin confidential/configuration access; test denial and IDOR.

## 3. Background audience preparation

- [x] 3.1 Implement private streamed upload/staging endpoints and resumable bounded import jobs with progress, cancel, error reports and expiry.
- [x] 3.2 Implement manual/CSV/XLSX readers, canonical validation/normalization, formula/archive/row limits and explicit duplicate handling.
- [x] 3.3 Add authorized explicit CRM email/name field mapping for hopper/capture sources without mutating source records or inferring consent.
- [x] 3.4 Implement owned contacts, allowlisted saved segments, documented permission assertions and suppression eligibility previews.
- [x] 3.5 Move confidential document preparation into bounded staging jobs while preserving exact mapping, inspectors, scanner policy, encryption and all-or-nothing approval.
- [x] 3.6 Test malformed formats, interrupted/replayed imports, cancellation, consent rules, confidential mappings and configured-size memory/query bounds.

## 4. Composition and approval

- [x] 4.1 Add lazy-loaded self-hosted drag-and-drop editor, raw HTML mode and separately stored design JSON.
- [x] 4.2 Implement server HTML/style/URL sanitization, safe merge-tag contexts, scriptless desktop/mobile preview and accessible block-placement alternatives.
- [x] 4.3 Add version-3 approved snapshots and bounded freezing while preserving version-1/2/plain-text/PDF behavior.
- [x] 4.4 Add explicit operator-only rendered synthetic template test while preserving independent SMTP tests and campaign approval.
- [x] 4.5 Test malicious HTML/variables, old snapshots, missing values, sender/test isolation and frozen-document regressions.

## 5. Lifecycle scheduling and reliable delivery

- [x] 5.1 Implement guarded lifecycle transitions, legacy state mapping and separate uncertainty/attention visibility.
- [x] 5.2 Add UTC/IANA schedules, invalid/ambiguous-time validation, reapproval, due-command locking and scheduler heartbeat diagnostics.
- [x] 5.3 Implement durable dispatch intents, bounded native Laravel batch loading, attempt correlation and generation-aware atomic recipient claims.
- [x] 5.4 Add shared provider/account rate limits, campaign pacing, safe transient backoff and separate deferral versus submission attempts.
- [x] 5.5 Implement pause/resume/cancel and verified-recipient retry without re-enabling accepted/uncertain/in-flight recipients.
- [x] 5.6 Add outbox/pending recovery, worker interruption handling, stale claims and actionable readiness/stall diagnostics.
- [x] 5.7 Test dispatch gaps, duplicate jobs, scheduled expired documents, terminal derivation, safe retries and revocation of live/local policy.
- [ ] 5.8 Exercise MySQL concurrency for starts/schedules/claims/rate limits/pause/cancel/retries and record evidence.

## 6. Provider feedback suppression and tracking

- [x] 6.1 Add Super Admin provider identity/limits/encrypted webhook configuration and opaque SMTP metadata correlation.
- [x] 6.2 Implement authenticated HTTPS Postmark receipt validation, durable deduplication and queued delivery/bounce/complaint processing.
- [x] 6.3 Handle early/duplicate/out-of-order feedback, exact attempt correlation and provider-event versus local-send races without counter inflation.
- [x] 6.4 Add hard-bounce/complaint/manual suppression management and enforce applicable suppression at approval and immediately before transport.
- [x] 6.5 Preserve historical signed unsubscribe and add opaque confirmation/one-click POST with idempotent global marketing suppression and campaign attribution.
- [x] 6.6 Add default-off privacy-aware open/click observations, frozen safe destination mapping and minimal event retention; disable confidential tracking.
- [x] 6.7 Test spoofed/mismatched events, event retries, suppression bypasses, tracking tokens/privacy, redirects and unsubscribe scanner behavior.

## 7. Dashboard histories reports and notifications

- [x] 7.1 Implement scoped recipient-based metrics, explicit coverage/denominators and acceptance-versus-delivery/uncertainty definitions.
- [x] 7.2 Add paginated campaign/recipient/event search and filters, safe diagnostics and streamed redacted/authorized identifiable reports.
- [x] 7.3 Integrate terminal/stalled notifications with durable idempotency and the existing notification UI; audit all sensitive email actions.
- [x] 7.4 Add overview charts, audience/template/suppression workspaces, lifecycle controls and visible/stale progress polling using the existing design system.
- [x] 7.5 Test aggregate accuracy, event/attempt deduplication, identifiable export permissions/formula injection and repeated notification processing.

## 8. Browser regression and scale validation

- [x] 8.1 Run Playwright MCP isolated flows for Super Admin/Admin/denied roles, all audience sources, segments, composition, previews/test readiness, approval/scheduling and lifecycle controls; record synthetic versus live test limits.
- [x] 8.2 Validate progress/search/exports/suppression/unsubscribe, browser errors and failed network requests.
- [x] 8.3 Validate keyboard and 375/768/1024/1440px light/dark layouts; refine from actual rendered evidence and run the Impeccable detector once over finished UI.
- [x] 8.4 Run meaningful configured-size import/delivery/export/aggregate benchmarks and document measured memory/query behavior.
- [x] 8.5 Run relevant PHPUnit/JS tests, build, Pint, diff and strict change/main-spec OpenSpec validation; check affected existing CRM boundaries and record repository-wide limitations. Required MySQL concurrency execution remains separately open in 5.8.

## 9. Documentation operational acceptance and closeout

- [x] 9.1 Update operator/deployment/provider/security/privacy/retention docs, dependency/configuration examples and matching Supervisor/scheduler runbooks.
- [ ] 9.2 Verify deployed migration/assets/configuration, shared cache, supervised email workers and fresh worker/scheduler heartbeats through authorized access.
- [ ] 9.3 Verify approved SMTP connectivity, tiny approved mailbox delivery, authenticated provider feedback and final accurate reporting on the intended deployment.
- [ ] 9.4 Verify supervised interruption/recovery and cancellation/retry behavior without duplicate sending on approved operational fixtures.
- [x] 9.5 Synchronize final specifications and validation evidence with the actual implementation; leave any unavailable operational checks open.
- [ ] 9.6 Archive only after all application/browser/operational acceptance tasks pass; never claim production readiness from synthetic tests.

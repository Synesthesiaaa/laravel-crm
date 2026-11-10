# Email Campaign Management Implementation Plan

> **For agentic workers:** Use the approved design, TDD and the parallel-feature-development ownership rules. Execute independent modules against the contracts below; the primary agent integrates lifecycle and routes and performs final validation/review.

**Goal:** Extend the existing sender into the approved campaign management system without weakening confidential-document or uncertain-submission safeguards.

**Architecture:** Retain existing Laravel services and legacy paths. Add isolated audience, composition and tracking services, additive persistence and a durable lifecycle dispatcher. Recipient attempts remain the source of truth independently of Laravel batch counters.

**Tech Stack:** Laravel 12, PHP 8.4+, Blade/Alpine, Vite, database/Redis queues, GrapesJS newsletter, server-side HTML sanitization, streaming XLSX.

**Spec:** `docs/superpowers/specs/2026-11-11-email-campaign-management-design.md`, approved by user with “approve and implement”.

## Global Constraints

- Preserve version-1/2 snapshots and existing confidential document features.
- SMTP/provider configuration and confidential/transactional/payroll campaigns remain Super Admin only.
- Admin may manage owned attachment-free marketing; every endpoint and query enforces that scope.
- No blind retry after transport entry; observed SMTP acceptance is not confirmed delivery.
- No live migrations, provider account changes, production blasts or invented operational evidence.
- New HTML and XLSX dependencies must be maintained, compatible and locally bundled where applicable.

## Review Focus

- A cancelled/paused campaign must not regain eligibility through delayed or recovered jobs; exercise generations and claim races.
- A provider event can precede the SMTP return; event evidence must survive local completion/failure.
- Unknown CRM consent must never be inferred; test explicit field mapping and permission assertions.
- A new HTML template must not reinterpret historical plaintext or allow attribute merge-tag injection.
- Unsupported tracking/provider metrics must remain unavailable rather than displaying false zero delivery.

## Interfaces and ownership

Primary agent owns shared campaign/recipient/settings models, lifecycle persistence/services/jobs, existing delivery/preflight/transition services, routes, navigation, application configuration, dependency installation and overview/detail integration.

Audience module owns new audience/import/contact/segment models, migrations and `App\Services\Email\AudienceService`, import jobs/controller/requests, audience views and focused tests. Entry points: upload/stage -> import resource; poll/cancel/contacts/segments; ready imports append validated recipients to an owned draft only. Suppressions are consulted through `App\Services\Email\SuppressionService::blocked(string $email, string $purpose): bool`.

Composition module owns `App\Services\Email\TemplateComposer`, template controller/requests/views/editor JS/CSS and tests. API: `sanitize(string $html): string`, `render(EmailTemplate $template, array $values): string`, `snapshot(EmailTemplate $template): array`. Primary owns dependency commands and root routes/models. Template `body_format` defaults `text`; nullable `design_json` is a cast array. No confidential attachments in rendered operator tests.

Tracking module owns suppression/event/tracking models/migrations/services/controllers/requests/views/tests. API: `SuppressionService::blocked(string $email, string $purpose): bool`; `EmailTrackingService::decorate(string $html, EmailCampaign $campaign, EmailCampaignRecipient $recipient, ?string $attemptId): string`; scoped metrics via `CampaignMetrics::forCampaign(EmailCampaign $campaign): array`. Primary creates attempt model/table with UUID id, campaign/recipient IDs, generation, status, provider/message correlation and transport timestamps. Postmark SMTP metadata uses `X-PM-Metadata-attempt` with the UUID. Tracking may add its own recipient feedback fields. Parent owns configuration settings integration.

## Task 1: Baseline, persistence and policies

- [ ] Run existing email suites on isolated SQLite and record actual baseline.
- [ ] Write failing lifecycle/ownership tests in `tests/Feature/Admin/EmailCampaignLifecycleTest.php`; run and verify missing behavior.
- [ ] Add lifecycle/import/snapshot fields, attempt/outbox persistence, policy methods and backward-compatible model casts.
- [ ] Rerun new and existing tests; fix regressions before integrating downstream modules.

## Task 2: Audience module

- [ ] Write failing manual/CSV/XLSX/CRM/consent/ownership/import-cancel tests; observe failure.
- [ ] Implement staged private resumable imports, contacts and allowlisted segments using bounded jobs and explicit consent assertions.
- [ ] Test unsafe spreadsheets, duplicates, interruption, source scope and configured limits; add operator UI.
- [ ] Verify module tests and integrate routes and readiness gating.

## Task 3: Composition module

- [ ] Write failing sanitization/HTML rendering/merge context/legacy/plaintext/test-address tests; observe failure.
- [ ] Implement sanitizer, editor persistence, raw HTML/visual modes, sandbox previews and fixed synthetic rendered tests.
- [ ] Add lazy bundled GrapesJS UI with keyboard placement alternatives and responsive preview.
- [ ] Verify composition tests and integrate approved snapshot rendering.

## Task 4: Lifecycle dispatcher

- [ ] Write failing scheduling/DST/pause/resume/cancel/duplicate claim/outbox gap/backoff tests; observe failure.
- [ ] Implement `CampaignLifecycle` and `DispatchEmailCampaign` with generation-bound native batches and durable intents.
- [ ] Integrate atomically claimed attempts, shared account rate limits and explicit transport certainty into existing delivery.
- [ ] Add minute scheduler/recovery/worker diagnostics and idempotent creator database notifications.
- [ ] Verify legacy snapshots, document protection, safe retry and queued interruptions.

## Task 5: Provider feedback and reporting

- [ ] Write failing auth/correlation/duplicate/early/out-of-order/suppression/tracking/unsubscribe tests; observe failure.
- [ ] Implement durable Postmark receipts and queued event processing, suppression management and optional opaque tracking.
- [ ] Add scoped metric definitions, histories, safe exports and dashboard charts/polling.
- [ ] Verify provider-versus-transport races and all report denominators/coverage states.

## Task 6: Integration and acceptance

- [ ] Run focused PHPUnit, relevant JS/build/Pint and strict OpenSpec checks; inspect diffs and regressions.
- [ ] Use Playwright MCP with isolated fixtures/array transport for all roles and representative 375/768/1024/1440 widths; inspect browser errors.
- [ ] Exercise available MySQL concurrency and configured-size synthetic benchmarks; record unavailable checks explicitly.
- [ ] Perform a fresh independent whole-change review and address actionable findings.
- [ ] Update operator/deployment/security/privacy docs and synchronize actual OpenSpec behavior/tasks.
- [ ] Leave live SMTP/provider/worker/scheduler acceptance open until authorized deployment access and mailbox evidence are available; archive only after every required gate passes.

## Execution ledger

- 2026-11-11: User approved the concrete design and requested implementation. Continue without another planning approval. No commit/push/deployment is requested.
- Ruling: Use independent module ownership from the applicable parallel-feature-development skill; primary agent integrates shared files and validates the complete change.

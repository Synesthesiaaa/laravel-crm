# Validation record

Date: 2026-11-11 (Asia/Manila). Work remains on `codex/email-campaign-management`; no production migration, deployment, real email blast or provider-account change was performed.

## Automated application validation

- Existing focused email baseline: 54 tests / 434 assertions passed.
- Final complete Unit/Feature suite: **923 tests / 5,111 assertions passed**, using `php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --no-coverage --log-junit storage/framework/testing/email-full-suite-release.xml`. Runtime 2:45.489, process peak 140 MiB. The earlier 128 MiB CLI run exhausted memory; the increased limit applies to the test process, not application configuration.
- This includes new audience, template, lifecycle, provider feedback, suppression/privacy, protected import and migration tests plus existing CRM regressions. The navigation expectation was updated to include the new workspace, then the complete suite passed.
- Five MySQL lifecycle concurrency tests were added and integrated into the existing MySQL CI job. Local execution skipped without an explicitly opted-in migrated disposable DB. The dedicated localhost helper could not connect/create its isolated test DB; **no passing MySQL concurrency result is claimed**. No application database was migrated or dropped by that attempt.
- SQLite migration rollback/reapplication passed, preserving a historical campaign, recipient and escaped plain-text template across all five new migrations.
- JS suite: **21 tests passed**, including editor asynchronous source/visual selection, teardown and frame policy cases. Build passed with the expected large lazy editor chunk warning. GrapesJS/editor CSS are loaded on the editor surface.
- Pint passed over changed PHP files, including the final performance-test extension. `git diff --check` passed.

## Synthetic configured-limit benchmark

`tests/Performance/EmailCampaignManagementBenchmarkTest.php` passed at **10,000 recipients** with an array transport:

| Measurement | Observed |
| --- | --- |
| Audience preparation/apply | 35.173 seconds |
| Recipient snapshot freezing | 14.649 seconds |
| Single-recipient delivery | 29 queries |
| Aggregate report calculation | 0.013 seconds |
| Redacted streamed CSV export | 0.599 seconds / 47 queries |
| Measured peak memory growth | 10 MiB |

The export contained all 10,000 recipient records and excluded addresses. Large audience preparation and snapshot approval run in background jobs; their measured runtime does not block HTTP. These are isolated SQLite figures, not production throughput or MySQL locking evidence.

## Playwright MCP and design evidence

All browser work used the loopback-only fixture at `127.0.0.1:8879`, an isolated SQLite DB and array mailer. Separate browser contexts were used for Super Admin, Admin and Agent. No real users, lists, SMTP accounts or documents were used.

- Super Admin manual-entry audience: HTTP 202 staging, background parse, explicit apply, background approval, native batch dispatch, two array-transport acceptances, Completed, one owner notification and redacted final CSV were observed through the actual UI/worker.
- Admin campaign list showed only owned campaigns. Cross-owner campaign access and SMTP configuration returned 403; own detail/audiences/templates returned 200. Agent received 403 on each email administration surface.
- CSV upload and application, real XLSX upload/selected worksheet, explicit CRM field mapping and saved segment staging reached ready states through UI and worker processing. Invalid formats/formulas, suppression and consent cases were exercised in PHP tests.
- Scheduling with Asia/Manila, pause, resume preserving the future scheduled instant, cancellation and draft configuration were observed in the browser.
- HTML source, sanitized preview, visual editor lazy loading, content preservation across modes, block insertion and save were observed. Enter on the focused Add block button inserted a block. A deliberately delayed preview reproduced a separate initialization race (Visual selection incorrectly returned to source); after removing the redundant stale initialization request, the same 350 ms delayed-preview check passed with Visual selected and no page JavaScript errors. The operator-only synthetic test button displayed its SMTP readiness validation in the fixture; positive synthetic rendering/submission isolation is covered by PHP tests, not a live SMTP browser claim.
- Overview, detail, audience, template and SMTP pages were checked at representative widths. Detail returned 200 with **no page overflow at 375/768/1024/1440px** after a relation-return-type regression was fixed and pinned by an HTTP test. Light/dark screenshots include `output/playwright/email-campaign-completed-1440.png`, `email-campaign-1440-light.png`, `email-campaign-375-dark.png` and template captures.
- Charts loaded; live status/counts and stale/error handling were checked. Browser logs were inspected. Existing shell integrations in this isolated environment report Reverb ws/wss connection refusals, SIP credentials 422 and quick-form bootstrap 422. These are recorded, not suppressed; there is **no claim of a globally error-free browser or live telephony/Reverb acceptance**.
- Impeccable context/craft guidance was used. The mechanical detector reported one warning for Arial in a generated email starter template; it is an intentional email-client fallback, not a replacement application font. Current CSS/design tokens were preserved.

## Specifications and other checks

- Strict validation passed for this change and both synchronized capabilities (`email-campaign-management`, `secure-email-delivery`).
- Repository-wide OpenSpec validation reported 73 valid items and one failure in the untouched `optimize-crm-interface` change. That unrelated artifact was not rewritten.
- Composer audit reported advisories in existing Symfony components; npm audit reported existing tooling/dependency findings (including shadcn-related packages). The new editor, sanitizer and XLSX package names were not flagged in those audit outputs. This is not an exploitability assessment or a clean dependency-audit claim.
- Module reviews identified sender/provider lock-order, late-evidence and stale failed-job hazards that were addressed. Final primary review and regression checks were performed; no independent final whole-branch reviewer completed after the subagent service became unavailable.

## Required operational acceptance still open

- Passing MySQL multi-process execution, including claims, due scheduling, shared limits and feedback/interruption races.
- Deployment of migrations/assets/configuration, matching supervised delivery/preparation workers, shared cache and scheduler heartbeats.
- Approved real SMTP connectivity and test mailbox evidence, real authenticated Postmark callbacks, sender/domain/quota approval and accurate receiving-server reporting.
- Positive real PDF inspector/scanner/policy acceptance and supervised interruption/recovery on the intended host.
- Review of the existing dependency advisories and unrelated infrastructure/browser/spec issues as part of release readiness.

The change is deliberately **not archived** and is **not certified production-ready** while these operational gates lack evidence. Existing live/local opt-ins and all confidential delivery protections remain in force.

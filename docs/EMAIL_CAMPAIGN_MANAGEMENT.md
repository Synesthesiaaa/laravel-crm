# Email campaign management

The campaign workspace is `/admin/email-management`. It extends the existing protected document sender; `/admin/email-campaigns` remains the Super Admin document/legacy workspace. Both use the same recipients, approved transport snapshots and application suppression rules.

## Access

Super Admin manages all campaigns, SMTP/provider settings, suppressions and private document delivery. Admin manages owned, attachment-free marketing campaigns, templates, contacts and segments. Team Leader and Agent cannot use email administration. Ownership checks apply to JSON, report, preview and mutation endpoints as well as pages.

SMTP settings remain at `/admin/email-configuration`. Stored SMTP and webhook passwords are encrypted, hidden from model serialization and never repopulated into forms. Blank passwords preserve saved values. Existing environment-mailer fallback is retained; changing current settings does not rewrite approved campaign snapshots.

## Marketing workflow

1. Create a template at `/admin/email-templates`. Choose escaped plain text or HTML. HTML supports a self-hosted GrapesJS visual editor, source editing, keyboard block controls and desktop/mobile synthetic preview. Server sanitization applies to both editing modes. Remote raster images require configured HTTPS hosts; arbitrary CSS URLs, active content and attribute merge tags are rejected. Saved approved templates are locked.
2. Create a draft with a template and conservative batch/interval settings. Tracking is optional, off by default and requires explicit permission review. Draft configuration can be edited before approval; pending approval locks editing.
3. Open Audiences. Choose a draft and manual entry, CSV, XLSX, explicitly mapped CRM fields or an owned saved segment. Uploads are private; parsing runs in bounded background jobs. XLSX requires an explicit worksheet, rejects formulas and bounds archive/row/cell sizes. Existing CRM records do not establish permission.
4. Record actual opt-in or an explicit documented permission assertion with a source/reference, permission date and actor. Unknown, withdrawn and suppressed recipients are ineligible. Duplicate handling can keep the first occurrence or reject the list. Review counts and rejected-row errors, then explicitly apply the audience. Applying also runs in bounded jobs.
5. Approve and start, or approve and schedule with local time and an IANA timezone. Ambiguous daylight-saving times require an explicit UTC offset; nonexistent/past times are rejected. The HTTP request records an approval request; the preparation worker freezes authenticated content and recipient snapshots before dispatch. An intervening template/draft change invalidates approval.
6. Follow progress in the detail page. Polling pauses when hidden and backs off on failure. Recipient histories are searchable and paginated. Export is redacted by default; identifiable CSV requires a separate explicit selection and formula-safe output.

Merge tags are `{{name}}`, `{{email}}`, `{{employee_id}}`, `{{period}}`. Missing non-name values block approval. HTML variables belong in visible text, not URLs, styles or attributes. Generated PDF and OCR features remain in the protected Super Admin workflow. See [template composer](admin/email-template-composer.md).

## Private documents

The protected upload form now stages sources as authenticated encrypted chunks of at most 1 MiB plaintext each and returns a preparation page. A separate long-timeout worker decrypts into a private transient workspace, invokes the existing exact-mapping/scanner/ZIP/PDF checks and creates one draft atomically. Cancel/expiry removes staging; interruption can be resumed without duplicating a draft. The historical POST draft endpoint remains compatible for existing integrations.

Original document mapping, encrypted storage, hashes, retention, generated protected PDFs, payroll policy, dry-runs and explicit confidential-send confirmations remain required. HTML rendering is format-aware for newly selected visual templates; historical plain-text snapshots stay escaped. See [protected delivery guide](EMAIL_CAMPAIGNS.md). Real inspector/scanner acceptance on the intended host remains a deployment check.

## Status and certainty

New campaigns use Draft, Scheduled, Queued, Processing, Paused, Completed, Partially Failed, Failed and Cancelled. Needs attention remains available for unresolved uncertainty; historical Sending/Needs attention states remain readable.

- Pause stops new claims. Messages already in transit can finish. Resuming a future schedule retains its scheduled instant; resuming after all in-flight work finished derives the terminal state instead of remaining queued.
- Cancel invalidates queued generations and cancels unclaimed recipients. It cannot recall accepted messages.
- Native Laravel batches monitor job execution. Durable dispatch intents, generation checks and atomic recipient attempts prevent replayed application jobs from submitting duplicates.
- Infrastructure jobs use bounded exponential backoff. Account-wide database rate budgets apply before submission and are shared across campaigns/From identities. Deferral does not consume an SMTP submission attempt.
- A potentially submitted SMTP message is **uncertain**, including timeouts or interrupted workers. There is no exactly-once guarantee across an SMTP server boundary. Obtain provider evidence; do not indiscriminately run `queue:retry all`.
- Verified pre-send failures can be retried after revalidation. Uncertain, accepted, delivered and in-flight recipients are excluded. Super Admin records a provider case and accepted timestamp or verified no-submission through the protected review reconciliation control. Reconciliation itself never sends.
- Current suppressions and contact permission are checked immediately before submission. Revoking the approving account's access also blocks later sends.

## Accurate reporting and feedback

SMTP accepted means the outgoing transport returned success or verified provider evidence establishes acceptance. Completed means application processing finished. Neither proves inbox placement. Provider-delivery events mean receiving-server acceptance, which still does not prove inbox placement.

The first supported event adapter is Postmark. Configure its server ID, message stream, separate webhook Basic Auth credentials and account rate limit as Super Admin, then configure the provider to POST to the displayed HTTPS callback. Correlation uses an opaque attempt UUID in `X-PM-Metadata-attempt`; the receiver verifies HTTPS, credentials, server/stream, recipient, timestamp and attempt identity before persisting a minimal durable receipt. Duplicate/out-of-order/early events cannot inflate counters or erase verified evidence. Do not change webhook account/stream identity while older campaigns still require feedback without planning provider callback continuity. See [provider setup](admin/email-provider-feedback.md).

Delivered/bounced/complaint metrics are unavailable for plain SMTP or incomplete callback configuration. Opens/clicks are unavailable when tracking is off. Rates state their denominator; zero denominators display unavailable. `open_count` remains pending/sending accounting and is never used as an email-open statistic.

Optional tracking uses opaque tokens and frozen safe destinations; arbitrary redirect targets are rejected. Application tracking respects configured DNT/GPC signals and does not store raw IP/user-agent/location. Observations can include scanners and privacy proxies. Confidential campaigns are not tracked. Configure infrastructure/web-server access-log retention separately.

Marketing messages include a token-protected unsubscribe confirmation and one-click POST. GET does not unsubscribe because scanners visit links. Historical signed links remain valid. Marketing opt-outs do not imply transactional permission; hard bounce, complaint and applicable security/manual blocks are enforced separately. Only manual suppression entries can be removed through the administration UI.

## Deployment

Install Composer/npm lockfiles and build assets. PHP requires DOM, XMLReader and ZIP for the new sanitizer/streaming spreadsheet support. Deploy additive migrations `2026_11_11_100000` through `100400` before enabling these routes. Rehearse rollback using synthetic data; never drop new confidential metadata while retained originals still require controlled cleanup.

Supervise **two** workers:

```sh
php artisan queue:work email-database --queue=email-campaigns --sleep=1 --timeout=60 --max-time=3600
php artisan queue:work email-preparation --queue=email-preparation --sleep=1 --timeout=300 --max-time=3600
```

The preparation connection has `retry_after=600`, exceeding its 300-second timeout. Delivery has `retry_after=120`, exceeding its 60-second timeout. Connections/queue names must match configuration. Preserve one delivery consumer while legacy campaign pacing is in use. Web/worker/scheduler share database, application key, cache store and cache prefix. Supervisor examples are in `deploy/supervisor/`.

Run the Laravel scheduler every minute. It dispatches due schedules, reconciles stale attempts, recovers durable pending/outbox/approval work, maintains protected staging and prunes expired audience sources. `email-feedback:maintain` recovers unprocessed provider receipts and expires engagement/event records. Worker/scheduler failures remain visible; the application cannot restart an unavailable host or service.

`EMAIL_CAMPAIGN_LIVE_ENABLED=true` is required for real bulk delivery. Local additionally requires `EMAIL_CAMPAIGN_ALLOW_LOCAL=true`. Testing cannot use real transports. These flags do not bypass SMTP/worker readiness, authorization, consent, suppression or document policy. The separate synthetic/operator tests can send real mail when explicitly invoked with configured SMTP; use approved account addresses.

Useful settings: `EMAIL_PREPARATION_CONNECTION`, `EMAIL_PREPARATION_QUEUE`, `EMAIL_RATE_PER_MINUTE`, `EMAIL_MAX_RATE_PER_MINUTE`, `EMAIL_IMAGE_HOSTS`, `EMAIL_TRACKING_RETENTION_DAYS`, `EMAIL_EVENT_RETENTION_DAYS`. Existing recipient/file limits remain in `config/email_campaigns.php`. Provider credentials should normally be maintained in the Super Admin configuration screen. Environment fallback keys remain available for existing deployment management.

## Validation and operational acceptance

Development tests use isolated SQLite, synthetic recipients/documents and array transports. The browser fixture is explicitly local at `tests/Support/email-campaign-browser-fixture.php`; its seed/work commands never use the configured application database or real mailer. The 10,000-recipient benchmark exercises bounded preparation, snapshot freezing, one recipient delivery, aggregate reporting and redacted streamed export. Actual results and outstanding operational gates are recorded in [the validation record](../openspec/changes/extend-email-campaign-management/validation.md).

```sh
php artisan test --no-coverage tests/Feature/Admin/EmailCampaignLifecycleTest.php tests/Feature/Admin/EmailAudiencesTest.php tests/Feature/Admin/EmailCampaignTrackingTest.php tests/Feature/Admin/EmailTemplateComposerTest.php tests/Feature/Admin/EmailTemplateManagementTest.php tests/Feature/Admin/ProtectedEmailImportsTest.php
php artisan test --no-coverage tests/Performance/EmailCampaignManagementBenchmarkTest.php
npm run test:js
npm run build
```

The MySQL lifecycle race suite is integrated into CI and requires an explicitly opted-in, already migrated disposable database. The local helper `php tests/Support/email-mysql-validation.php` permits only localhost and the dedicated `laravel_crm_email_campaign_test` database, never the configured application database. It does not drop a database. Its inability to connect does not count as a passing concurrency check.

Production readiness additionally requires approved SMTP connectivity/mailbox evidence, real receiving-server events, verified sender identity/quota, actual inspector/scanner acceptance, supervised workers/scheduler, interruption recovery and required MySQL concurrency results. Keep those OpenSpec tasks open until evidence exists. No production messages, deployment changes or delivery claims are implied by development tests.

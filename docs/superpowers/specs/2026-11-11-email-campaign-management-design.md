# Email campaign management design

Status: approved by user (“approve and implement”); implementation in progress.
Date: 2026-11-11, Asia/Manila.
OpenSpec change: `extend-email-campaign-management`.

## Intent and constraints

Extend Laravel CRM's existing email module into an operational campaign platform covering audience preparation, composition, approval, scheduling, asynchronous sending, provider feedback and reporting. Reliability includes knowing when an outcome is unknown. Never infer delivery from SMTP acceptance, or retry a possibly submitted message simply because a job failed.

The current checkout uses Laravel 12, PHP 8.4+, Eloquent, Blade/Alpine, Vite, database/Redis queue connections, Horizon, Spatie activity logs and an established responsive design system. The email subsystem already implements encrypted SMTP passwords, CSV audiences, private document mapping, OCR, generated PDFs, approved snapshots, dedicated worker heartbeats, atomic claims and provider-evidence reconciliation. These capabilities remain supported.

Relevant owning sources: `EmailCampaignsController`, `EmailCampaignPreflightService`, `EmailCampaignDeliveryService`, `EmailCampaignRecipientTransitionService`, `MaintainEmailCampaigns`, `EmailCampaignPolicy`, `StoreEmailCampaignRequest`, `routes/admin.php`, `routes/console.php`, and the existing email tests. `LeadHopper.custom_data` and `AgentCaptureRecord.capture_data` hold possible CRM email fields; there is no first-class Contact model to assume.

## Approaches

1. **Incremental extension (recommended; agent proposal).** Add email-domain services, persistence and UI around the current sender. Preserve historical versions through explicit compatibility paths. This minimizes risk to confidential attachments and existing operators, while requiring careful lifecycle and snapshot regression tests.
2. **Replacement subsystem.** Rebuild campaigns behind a new set of tables and migrate history. This offers a cleaner starting point but duplicates the sender, document protection and reconciliation logic, substantially increasing migration and duplicate-send risk.
3. **External marketing application as the system of record.** Delegate audiences and sending to an external platform and synchronize reports into CRM. This reduces locally owned delivery machinery but conflicts with the requested CRM-centered workflow, private document controls and provider-neutral SMTP baseline, and introduces an external account/data dependency.

The user approved this incremental design and requested implementation.

## Goals, non-goals and anti-goals

- Deliver all requested application capabilities, including Admin-owned marketing workflows, Super Admin configuration, multiple audience sources, drag-and-drop/HTML composition, scheduling, lifecycle controls, bounded asynchronous processing and accurate analytics.
- Support ordinary SMTP with acceptance-only reporting and an initial concrete Postmark event adapter for delivery, bounce and complaint events. No unsupported provider will be represented as delivering events.
- Preserve private PDF/ZIP ingestion, OCR imports, generated protected PDFs, exact mappings, dry-runs, purpose separation, live/local opt-ins and existing signed unsubscribe links.
- Do not provision a provider account, change sender DNS, start production workers, run live migrations, send to real campaign lists or assert legal compliance without evidence.
- Do not create a parallel telephony architecture, remove historical campaigns, infer contact consent, expose documents to Admin, log credentials, or relax uncertain-submission retry protections.

## Permissions and ownership

- Super Admin retains access to all email campaigns, contacts, segments, suppressions and audit records, and exclusive access to SMTP/provider settings and confidential/transactional/payroll workflows.
- Admin can create and operate their own **marketing, attachment-free** campaigns, reusable templates, contacts and segments. Reusing another user's audience or template requires explicit policy authorization. Staff-facing PDFs and generated protected PDFs remain Super Admin only.
- Team Leader and Agent have no email management access. Public unsubscribe/tracking/webhook routes have narrow token/provider authentication contracts.
- Add policy methods for list/create/view/update/schedule/start/pause/resume/cancel/retry/report/test and audience actions. Apply authorization to Blade and JSON routes and every service mutation; query scoping must match authorization.
- Keep SMTP settings routes in the existing Super Admin group. Move only approved marketing routes into an Admin/Super Admin group with ownership/purpose policies. Apply the same rules to navigation, aggregate metrics, exports and notifications.
- Audit state changes, approvals, consent assertions, suppression changes, SMTP changes and reconciliations with actor IDs and safe metadata. Do not store passwords or message bodies in activity payloads.

## Persistence and compatibility

Use additive migrations, foreign keys, bounded strings, appropriate nullable defaults and indexes. Final names may follow existing conventions without changing these contracts.

- Extend campaigns with scheduling UTC instant, IANA timezone, original local time, import readiness, pause reason, dispatch generation, progress heartbeat, native batch references, tracking configuration, terminal-notification version and optional sender/compliance snapshot fields.
- Add `email_contacts`, tags/segment definitions, consent provenance and campaign-recipient source references. Consent stores status, asserted/source time, source description and actor; historical unknown consent stays unknown.
- Add private import-run records with owner, purpose, phase/cursor, counts, cancellation, error report and expiry; source bytes live on a private disk. Import idempotency is keyed by run/row and campaign/normalized email.
- Add a dispatch-intent/outbox record unique by campaign/generation and a durable recipient attempt record. Record attempt UUID, state, transport boundary, provider/message correlation, safe error code and retry eligibility.
- Add event receipts and recipient delivery/engagement fields with provider/event deduplication keys, indexed event time and campaign/recipient foreign keys. Retain a minimal event allowlist, never whole webhook payloads containing addresses/content unnecessarily.
- Add suppression records with normalized email, reason, purpose scope, source, creator and optional review information. Preserve `email_opt_outs` and consult it throughout the compatibility period.
- Add opaque tracking-link mappings, hashed unsubscribe/tracking tokens and terminal notification/outbox records with unique event-version keys.
- Keep legacy `sent`, `sending`, `needs_attention`, `open_count` and version-1/2 snapshots readable. `open_count` remains unfinished-work accounting; introduce clearly named engagement fields.
- Introduce a version-3 snapshot for new composition, tracking, consent/sender policy and dispatch metadata. Authenticate compact campaign headers and individual recipient snapshots. Legacy snapshot verification remains unchanged; never reinterpret old plain-text bodies as HTML.

## Audience preparation

- Manual entries, CSV, XLSX and CRM source selections all feed the same validated staging/approval flow. Upload endpoints stream bounded files to private storage and return `202` with an import resource; they do not parse whole lists or PDFs in the HTTP request.
- Use queued, resumable jobs with bounded pages/chunks. Cancellation stops new work and cleans staging data; invalid imports cannot become sendable. Import previews expose safe row errors, counts for duplicates/invalid/suppressed records and a bounded sample.
- Retain current generic/strict manifest semantics where required for compatibility. New generic marketing imports expose an explicit duplicate policy; confidential manifests always reject duplicate emails/files and mismatches. Use one canonical address-normalization helper without rewriting historical frozen addresses.
- Use a maintained streaming XLSX reader compatible with PHP 8.4+. Accept `.xlsx`, not macros/legacy `.xls`; reject formulas, unsafe ZIP expansion, excessive sheets/rows/cells and oversized values. Select one worksheet explicitly. Retain CSV BOM/header/line safeguards.
- CRM import offers only authorized `LeadHopper`/`AgentCaptureRecord` campaign sources. The operator selects the named email field and optional name/metadata fields. Validate every value; do not infer an email from arbitrary JSON, use staff accounts as customer contacts, or mutate CRM source records.
- Saved segments use allowlisted filters for contact tags, originating CRM campaign/source, consent state and suppression eligibility. Use parameterized query scopes; no user SQL or executable expressions. Freeze selected recipients at approval, including a source/segment description.
- Proposed consent eligibility: new marketing campaigns admit only `opted_in` contacts or contacts with an explicit operator permission assertion carrying source description, asserted permission date and actor. `unknown`, `withdrawn` and applicable suppressed contacts cannot be approved. An import permission checkbox alone does not silently turn unknown CRM contacts into opted-in contacts; a separate documented assertion is required. Existing unfrozen drafts need that assertion at approval. Already frozen version-1/2 campaigns retain their originally approved permission contract plus current opt-out/suppression checks; no consent is fabricated or backfilled. Transactional/payroll purposes require documented authorized purpose rather than marketing opt-in and still honor applicable security/hard-bounce/complaint blocks.
- Existing default size caps remain in effect until measured validation supports higher limits. Storage and import processing must stay bounded even when limits are raised. Stage original PDFs for background validation/encryption with existing inspectors, scanner policy and exact mapping; queue approval waits for completion.

## Composition and UI

- Use a self-hosted GrapesJS newsletter editor, loaded only on template-editing routes through Vite. Use an appropriate maintained server-side HTML sanitizer; verify versions, licenses and PHP compatibility before adding dependencies.
- Persist builder JSON separately from sanitized HTML and an explicit body format. Both raw HTML and visual-editor output pass through the same server sanitizer. Allow a documented subset of email table/layout/inline-style markup; reject scripts, forms, event handlers, embedded objects, active URLs, CSS imports and unsupported CSS.
- Permit merge tags in subject and visible text only. Escape values for their context; reject tokens inside attributes, styles or URLs. Retain `name`, `email`, `employee_id`, `period`; expand only through an explicit allowlist and documented missing-value rules.
- Preview sanitized output in a scriptless sandboxed iframe. Do not fetch arbitrary remote image URLs on the server. Restrict remotely referenced images to configured public HTTPS raster hosts; no new asset-upload service in the initial editor.
- Provide desktop/mobile preview sizes and labelled keyboard alternatives for block placement/reordering. Plain-text templates remain editable and valid.
- Keep the synthetic SMTP/self-test. Add a separate explicit rendered-template test to the current operator's verified/valid account address using synthetic merge values and no confidential attachments; never alter campaign approval or send to uploaded addresses.
- The Operate UI reuses existing Blade panels, buttons, fields, theme variables, typography and ApexCharts. Add routes for overview/history, campaign preparation/review, templates, contacts/segments and suppressions; SMTP stays separate.
- Overview: compact metric rows, one delivery-performance chart, searchable/filterable campaign table, queue status. Campaign detail: purpose/state/next action, progress and metrics, recipient status/search, event history and controlled actions. Keep privacy-sensitive reports restricted and redacted by default.
- Use existing 44px targets, labels, visible focus, error summaries and live status announcements. Poll scoped JSON progress at a bounded interval while visible; pause on hidden tabs, back off on failures and prevent overlapping requests. Provide last-updated/stale/refresh states.
- Validate 375/768/1024/1440px widths in light/dark modes, keyboard operation and long/empty/error/loading states. Preserve the current executable CSS tokens over stale design-sidecar values.

## Lifecycle, scheduling and queue delivery

New campaigns use `draft`, `scheduled`, `queued`, `processing`, `paused`, `completed`, `partially_failed`, `failed`, `cancelled`; legacy states map explicitly for display/actions. Import readiness and delivery certainty are separate from lifecycle state.

- Only ready/approved drafts can start or schedule. Store schedules as UTC plus IANA timezone and original local input. Reject past times, DST gaps and ambiguous local times without an explicit offset choice. Freeze approved content/audience/transport at scheduling; changing a schedule/content requires explicit reapproval.
- A scheduler command runs every minute, locks due rows and records dispatch intents transactionally. It applies the same live, worker, purpose and document-integrity gates as immediate start. Delayed campaigns get an actionable diagnostic; scheduling does not bypass expired-document policy.
- Outbox processors and recovery commands dispatch bounded native Laravel batches, recording generation and batch identifiers. Batch completion counts are execution diagnostics, never proof of message delivery. Recipient rows/attempts remain the authority if dispatch or batch registration is interrupted.
- Claim a recipient atomically only when the campaign/generation is eligible, consent/suppression/policy checks pass and no previous accepted/uncertain attempt exists. One message has one To address and no CC/BCC.
- Apply shared provider/account rate limits and campaign pacing before entering transport. Super Admin configures conservative provider limits; multiple consumers share the same database/Redis limit state. Rate-limit deferrals must not exhaust SMTP attempts.
- Use exponential backoff (initially 30/120/600 seconds, configurable bounded attempts) for safe transient pre-submission failures and explicit adapter-confirmed non-acceptance. Permanent validation/policy failures become blocked. Transport errors/timeouts/worker deaths after the transport boundary become uncertain; do not automatically retry them.
- Generic SMTP cannot guarantee exactly-once external delivery. Database claims and generation checks prevent concurrent/replayed application sends; uncertainty reconciliation prevents blind resend across the external boundary. Idempotency keys do not make an SMTP server idempotent.
- Pause stops new claims and preserves pending work. Already submitted/in-flight work can finish. Resume creates a new dispatch generation for eligible pending work after checks; old queued jobs become harmless. Cancel stops future claims and marks unclaimed work cancelled; cancellation never recalls accepted messages.
- Terminal derivation distinguishes all accepted/skipped, mixed failures and total failures. Uncertain outcomes remain visible as attention with a reason and evidence reconciliation controls; neither pause nor cancel silently resolves uncertainty.
- Manual retry selects only safe failed/blocked recipients after revalidation. Reconciled verified no-submission can become retryable; accepted, delivered, sending and uncertain recipients cannot. Preserve provider evidence and attempt history.
- Maintain matching worker/scheduler heartbeat diagnostics, stale-claim reconciliation, pending/outbox recovery and fail-closed checks. Workers are externally supervised; application code cannot ensure progress if the scheduler, queue or host is down. Notifications and the dashboard expose those conditions instead of promising automatic infrastructure repair.

## Provider events and privacy

- First adapter proposal: **Postmark** with separately configured encrypted webhook credentials, HTTPS-only inbound Basic Auth, optional configured IP restrictions and expected server/message-stream identity. Ordinary SMTP remains supported with explicitly unavailable provider-delivery metrics.
- Send opaque attempt metadata through documented `X-PM-Metadata-*` headers. Validate recipient and account/stream correlation against the recorded attempt; quarantine mismatches without updating campaign totals. Persist minimal durable event receipts before background processing and acknowledge only after receipt persistence.
- Deduplicate using the stable Postmark webhook trace identifier where available and a deterministic event-specific fallback. Handle duplicate, out-of-order and feedback-before-local-acceptance events monotonically; a later local SMTP result must not erase verified provider evidence. Unsupported events cannot change delivery state.
- Delivery records mean provider-confirmed receiving-server acceptance, not guaranteed inbox placement. Hard bounces and complaints create scoped suppressions before the next send. Transient bounces are recorded separately with an explicit review policy; never erase unsubscribe/complaint suppressions through import.
- Optional application open pixels and click redirects default off and are unavailable for confidential/payroll messages. Persist independent per-campaign consent/privacy settings and honor configured opt-out/DNT policy where applicable. Do not persist raw IP addresses, user agents or inferred identity/location.
- Tracking uses opaque tokens and frozen allowlisted HTTP(S) destinations. Never accept a caller-supplied destination or rewrite unsubscribe links. Recorded opens/clicks are observations that can include bots/privacy proxies, not proof of a human reader. Provider open/click events are processed only when approved tracking settings permit them.
- Preserve historical signed unsubscribe GET confirmation/POST behavior. New marketing messages include opaque confirmation links and one-click POST support, with campaign attribution and an idempotent global marketing opt-out. Tokens do not expose email addresses; GET alone does not unsubscribe because link scanners visit URLs.

## Reporting and notifications

- Report SMTP accepted, provider delivered, hard/soft bounced, complained, safe failures, blocked, uncertain, skipped, cancelled, observed unique opens/clicks and attributed unsubscribes separately. Sent means accepted, never delivered.
- Define counts as unique campaign recipients and expose event totals separately. Use attempt-aware effective delivery outcomes so retries/events do not inflate recipient totals. Use campaign-created/scheduled cohorts for period filters and label chart/event timezones.
- Delivery/bounce rates use accepted recipients as denominator when coverage exists; unique observed-open/click rates use delivered recipients with a known provider denominator, otherwise an explicitly labelled accepted-recipient basis. Zero denominators yield unavailable values. Show coverage/disabled states rather than artificial zero delivery or engagement for unsupported providers or historical data.
- Retain a redacted export; add authorized, explicitly selected identifiable exports with formula-injection protection, streamed/chunked generation and no SMTP secrets, document contents or tokens. Search recipients server-side with bounded pagination and indexed campaign/status/date filters.
- Queue idempotent creator notifications for terminal success/partial failure/failure and stalled campaigns, with a campaign link and safe counts. Use the existing notification UI integration and database channel by default so sending infrastructure failure does not hide the alert. Mail notifications require explicit configuration and use the separate operational channel, not campaign recipients.
- Record audit and event retention separately from private-original retention. Add scheduled expiry for staging imports/tokens/minimal events according to documented configurable retention; preserve suppression evidence until an authorized retention decision permits removal.

## Migration and rollout

1. Run existing focused email tests on isolated SQLite, inspect MySQL schema/connection through Boost and record baseline limitations. No production credentials or live campaigns are used.
2. Add reversible schema with no destructive status rewrite/backfill. Rehearse migration/rollback using synthetic databases; never roll back populated new confidential records without the existing retention procedure.
3. Build and test backend capabilities in dependent stages, then the UI. Keep a rollout flag for new campaign creation/lifecycle until migration, assets, workers and scheduler are ready; existing campaigns continue through their legacy path.
4. Deploy migrations/assets/code with approved operations; restart only matching email workers. Configure provider limits, authenticated webhook identity/credentials and HTTPS. Verify shared cache, queue retry_after versus timeout and a fresh worker/scheduler heartbeat.
5. Exercise a tiny authorized campaign and observed provider events on the intended deployment with an approved mailbox. Verify recipient-server delivery, cancellation, safe retry, interruption reconciliation and reporting. Never label this passed from array transport results.
6. Synchronize OpenSpec after observed behavior and review. Archive only after all required application and operational acceptance tasks pass. Leave operational tasks open if credentials/access or provider evidence are unavailable.

## Validation and completion criteria

- Automated feature/unit tests: roles/ownership/IDOR, import format/limits/resume/cancel/deduplication, consent/suppression, HTML sanitization/personalization, legacy snapshots/PDF policies, DST scheduling, state transitions, claims/generations, safe retries/backoff, batch failures, worker interruption and outbox recovery.
- Provider contract tests: authenticated/unauthenticated events, wrong account/stream/recipient, duplicates/out-of-order events, early events, hard/soft bounce/complaint suppression and event retry after processing failure.
- Tracking/unsubscribe tests: token tampering, disabled/privacy mode, redirect restrictions, duplicate observations, one-click POST, GET scanner safety and historical signed links.
- MySQL multi-process tests: concurrent starts, due-schedule claiming, shared limits, pause/cancel/retry races, event-versus-send result and atomic counters. SQLite alone cannot establish lock correctness.
- Meaningful synthetic scale tests at configured limits: bounded import memory/runtime, bounded recipient delivery queries, streamed exports and aggregate query behavior. Record actual figures, not speculative throughput claims.
- Playwright MCP: authenticated Admin/Super Admin/denied roles, manual/CSV/XLSX/CRM audiences, segments, editor/HTML/preview, approvals/schedules/controls, progress/search/export, validation and keyboard/mobile behavior. Use isolated fixtures/array transport, inspect console and failed requests.
- Run focused PHPUnit, relevant JS tests, build, Pint, diff checks and strict OpenSpec validation. Broaden regression testing when affected boundaries warrant it.
- Operational acceptance remains mandatory: approved SMTP connectivity and mailbox evidence, deployed provider webhooks, supervised workers, scheduler and interruption recovery. These may require user-provided account/configuration or server access; do not mark them complete without evidence.

## Risks and decisions for approval

- SMTP uncertainty and queue duplication are unavoidable distributed-system boundaries; preserve evidence and fail closed rather than promising exactly-once delivery.
- Proposed dependency choices and Postmark as the first event adapter are agent recommendations, not existing user choices. They do not require purchasing a new account to keep ordinary SMTP working.
- Admin-owned attachment-free marketing access is a deliberate authorization expansion; document and test it separately from unchanged Super Admin confidential workflows.
- The first CRM source mapping uses explicit fields from existing capture/hopper data; imported consent must be supplied/asserted, never inferred from CRM presence.
- Operational credentials, approved sender/provider identities, approved test mailbox and authenticated deployment access have not been established in this task. Application implementation can be validated synthetically; production acceptance cannot be invented.
- Approval requested for this incremental design, particularly the initial Postmark adapter and Admin-owned marketing boundary. Exact package versions and routine file organization remain implementation decisions after compatibility/security checks.

## Builder review

A read-only builder review identified one unresolved rule: which consent states permit marketing delivery and how historical campaigns behave. The Audience preparation section now specifies a proposed eligibility rule and legacy boundary. This is an explicit design proposal for approval, not a claim that the user has selected a legal/compliance policy. No application code has been written under this design.

## Technical references inspected

- Laravel Boost application information and Laravel 12 queue/scheduler documentation, including native batching, rate-limit job deferral and single-server scheduling. Scheduling lock correctness still requires the project's shared-cache and database tests.
- [Postmark SMTP metadata](https://postmarkapp.com/developer/user-guide/send-email-with-smtp): documented SMTP metadata headers and asynchronous bounce feedback.
- [Postmark webhook overview](https://postmarkapp.com/developer/webhooks/webhooks-overview) and [delivery webhook](https://postmarkapp.com/developer/webhooks/delivery-webhook): HTTPS authentication, stable receipt identification, delivery correlation and retry handling. No provider account or live webhook was contacted.

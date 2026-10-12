# AGENTS.md — Laravel CRM Email Campaigns & Secure Document Delivery

> Repository: `Synesthesiaaa/laravel-crm` (`main` inspected 2026-10-13).  
> Focus: **Super Admin → Email Campaigns** and **Email Configuration**.  
> This is an implementation specification for coding agents, **not** a claim that the features already exist.  
> If a root `AGENTS.md` or repository-specific instructions are subsequently added, merge these rules without replacing unrelated guidance.

## 0. Mission

Act as a senior Laravel engineer, security engineer, queue/reliability engineer, QA engineer, and pragmatic product designer. Upgrade the existing CRM email-blasting feature into a reliable **one-message-per-recipient document delivery system**. Support both:

1. **Shared attachments:** one or more documents (e.g., company circulars, forms, announcements) delivered to every authorized recipient in the campaign.
2. **Recipient-specific attachments:** each recipient receives **their own PDF** (e.g., payslip/payroll, invoice, statement, certificate or personalized letter) according to an explicit, verified manifest.

**Highest-priority invariant:** A recipient must NEVER receive another recipient's document. If mapping, permissions, file integrity, or delivery readiness cannot be verified, **fail closed and do not dispatch that recipient**. A safe partial/blocked send is better than a privacy breach.

Improve reliability, queue visibility, usability, and test coverage while retaining all existing working features. Do not start a real mail blast or use real employee documents during development or tests.

## 1. Ground-truth repository inventory (verified on `main`)

Do not assume this inventory remains current: inspect the local working tree, branch, `git status`, and all relevant files before editing. Preserve uncommitted work.

| Area | Existing implementation | Impact |
| --- | --- | --- |
| UI | `resources/views/admin/email-campaigns/index.blade.php` | Blade/Alpine-based campaign + template + CSV UI; extend this design system instead of rewriting the whole CRM. |
| Campaign endpoints | `app/Http/Controllers/Admin/EmailCampaignsController.php` | Templates, OCR imports, CSV parsing, draft, send, cancel. |
| SMTP | `app/Http/Controllers/Admin/EmailConfigurationController.php`, `app/Services/EmailSmtpService.php`, `app/Models/EmailSmtpSetting.php` | Super Admin SMTP settings already exist. Reuse and safeguard them. |
| PDF documents | `app/Services/EmailCampaignDocumentService.php` | TCPDF generates password-protected PDFs **from text**. This does **not** implement attaching a user's existing PDF file. OCR import extracts text for templates; it is not a document-upload attachment workflow. |
| Async delivery | `app/Jobs/SendEmailCampaignBatch.php` | Named `email-campaigns` queue; current job processes offset-based recipient batches. |
| Data | `EmailCampaign`, `EmailCampaignRecipient`, `EmailTemplate`, `EmailOptOut` | Extend through additive migrations and relationships; do not destroy existing campaign history. |
| Config | `config/email_campaigns.php`, `config/queue.php`, `config/mail.php` | Queue and OCR binaries configured separately. |
| Docs | `docs/EMAIL_CAMPAIGNS.md` | Describes current email campaign setup, CSV import, PDF generation, queue worker. Update when implementing. |
| Tests | `tests/Feature/Admin/EmailCampaignsTest.php`, `tests/Feature/Admin/EmailConfigurationTest.php` | Preserve all existing expectations and add comprehensive regression tests. |
| Routes | `routes/admin.php` | Existing endpoints are Super Admin scoped. Do not widen them accidentally. |

**Observed operational gap:** The repository's `composer.json` development command starts a worker for `telephony,default`, **not** `email-campaigns`. The documented email-specific worker must also be running. A 1-second batch delay does not make delivery happen without a consuming worker; do not silently change the worker to `sync` or run email sending inside the HTTP request.

**Current limitations to fix:** Campaigns are created from an `email,name` CSV; the worker can attach a single generated template PDF; it cannot upload and map unique PDF files per recipient. The worker currently adds marketing-style subscription/unsubscribe text to every email, which is inappropriate for payroll/transactional notices. The implementation records transport acceptance as `sent`, which must not be misrepresented as confirmed inbox delivery.

## 2. Explicit scope and non-goals

### Implement

- Existing generic email campaigns remain functional, with reusable templates, CSV upload, SMTP setup, generated PDF (if enabled), and unsubscribe for marketing campaigns.
- Introduce a **campaign purpose** field with at least `marketing`, `transactional`, and `payroll`. Purpose controls authorization, disclosures, suppression, and logging policies.
- Introduce **attachment mode**: `none`, `shared`, `recipient_specific`, and, if cleanly supportable, `shared_and_recipient_specific`. Existing generated template PDFs may coexist if explicitly selected; show the final attachment list per recipient.
- Upload already-created PDFs **as files** without converting them to template text. Generic shared documents may support other safe file formats later; keep initial personal-document uploads **PDF-only**.
- Bulk import through **recipient CSV + ZIP of PDFs**, plus convenient multiple-PDF upload for small batches. Each recipient-to-file assignment is explicit.
- Download a CSV template specific to personalized attachments, while preserving the existing `email,name` CSV endpoint and its tests.
- Preflight review, mapping validation, per-recipient preview, dry-run, final confirmation, sending progress, error reporting, and secure document lifecycle.
- Fix practical queue/worker diagnostics and eliminate avoidable stalled batches.

### Do not

- Replace Laravel, Blade, Alpine, the CRM admin navigation, or the entire UI framework just for this module.
- Change VICIdial, telephony, call history, payroll calculation, HR master data, or unrelated campaigns.
- Automatically generate salary calculations, infer employee email addresses from filenames, or invent compensation data.
- Email a sample payslip to the wrong person, send production emails in test suites, or import real payroll records into fixtures.
- Enable unauthenticated/public document URLs, CC/BCC delivery, or public filesystem storage.
- Silence import errors, fall back to sending without required attachments, or claim provider acceptance equals confirmed delivery.

## 3. Intended usage journeys

### A. Confidential payroll / individual payslips

1. Authorized Super Admin or explicitly granted HR/payroll operator opens **Email Campaigns → New campaign** and chooses **Payroll / Individual PDFs**.
2. Selects an approved template and authorized sender; optional variables include `{{name}}`, `{{email}}`, `{{employee_id}}`, `{{period}}`, as allowed by a validated allowlist. Missing variables are reported rather than silently left in messages.
3. Downloads the **Personalized PDF CSV template**, fills employee identifiers and recipient email addresses, then uploads the completed CSV and a ZIP of PDFs (or selects multiple PDFs for small sends).
4. System resolves **each CSV row to exactly one unique PDF inside the uploaded set**. Operator views an approval table with recipient, masked identifier/email as appropriate, PDF filename, file status, and validation errors.
5. Operator reviews an authorized PDF preview for a specific recipient, sees aggregated success/error counts, performs an internal dry run, and expressly confirms the sender/recipient/file mapping and authorized payroll purpose.
6. Only after **all required mappings are valid** and the sender/worker checks pass can the campaign be queued. The manifest and attachment assignment are frozen when queued.
7. Each outgoing message goes to exactly **one** address and attaches only that recipient's validated document(s) and any intentionally shared document(s).
8. Dashboard displays queued, sending, accepted by provider, blocked, skipped, failed, cancelled, and uncertain states with a safe retry/reconciliation workflow.
9. Retain documents only for an approved configurable period and purge all temporary and retained copies using a secure lifecycle, with auditable deletion metadata.

### B. Other personalized documents

Use the same explicit mapping workflow for invoices, statements, certificates, letters, and other personal PDFs; copy/labels change based on purpose but **all confidentiality requirements remain**. Never assume that an invoice document is safe to broadcast to a group.

### C. Shared bulk announcement

A marketing or informational campaign can attach one or more safe shared PDFs to all eligible recipients. **Marketing** campaigns honor email opt-outs and include the existing valid unsubscribe method; payroll and other necessary transactional notices use a truthful, purpose-appropriate message and do not misleadingly assert that recipients subscribed to promotional updates. Determine transactional-lawful-basis and HR sending policy with the organization; never bypass explicit blocks set by compliance/security administrators.

## 4. Required import format and mapping rules

Add a second, accessible **Download personalized PDF CSV template** action. Preserve the old CSV contract. Example:

```csv
email,name,employee_id,period,pdf_filename
alice@example.com,Alice Reyes,EMP-1001,2026-09,EMP-1001-2026-09.pdf
bob@example.com,Bob Santos,EMP-1002,2026-09,EMP-1002-2026-09.pdf
```

`email` and `pdf_filename` are mandatory for personalized PDF campaigns; `name`, `employee_id`, `period` are optional for non-payroll use unless the selected payroll policy requires them. Support UTF-8 (with/without BOM), reject malformed records with row-numbered errors, and normalize emails conservatively without modifying the local part in ways that could change recipient identity. Do not strip or modify existing valid filename characters without a clear validation error.

Validation requirements:

- Exactly **one** manifest row per recipient email in sensitive campaigns. Reject duplicates, case-folding collisions, empty key fields, and conflicting document mappings; do not keep the "last" row silently as the older generic importer does.
- Require an exact **one-to-one** mapping between manifest entries and named PDF files for payroll. Match filenames only against the uploaded archive's parsed entry list, not by `basename` guessing, email matching, similar names, order, index, or employee-name heuristics. Require unique filenames and reject ambiguous paths/case collisions. Report extra/unmapped files without sending them.
- Validate extension **and actual PDF signature/parseability**. Enforce configurable total, per-file, file-count, and uncompressed archive limits; use narrow MIME allowlists; scan for malware through an approved scanner if available and define safe fail-closed behavior where scanning is mandated.
- ZIP ingestion must reject absolute paths, `../` traversal, drive-prefix paths, symlinks, encrypted or unsupported archives unless explicitly supported, archive bombs, nested archives, and duplicate normalized paths. Extract to isolated random directories with restrictive permissions and cleanup in `finally`/scheduled recovery.
- Reject missing, empty, unreadable, corrupt, password-protected-with-unknown-key, or oversized required PDFs before queuing; **never send a message without its required document**.
- The server must not accept arbitrary client-side storage paths, `file://` references, external document URLs, or CSV formulas as commands. Escape CSV-derived values in UI and mail content; protect downloadable reports against CSV formula injection.
- Store a cryptographic SHA-256 hash, safe filename metadata, size, MIME, ownership, campaign ID, recipient ID, and verification status. Do not use predictable public filenames/paths as an authorization boundary.
- For payroll, present a blocking review of the final **recipient ↔ PDF** mapping. If any row fails validation, block the whole payroll blast by default; an administrator may correct the import and revalidate, not silently skip employees.
- Show sample filenames but never place sensitive compensation data or complete PDF contents in logs, exception messages, HTML hidden inputs, browser local storage, or telemetry.

## 5. Private storage, encryption, and confidentiality

- All original and processed documents go to a **private, non-public Laravel filesystem disk** (`storage/app/private/...` or a private object storage bucket). Use server-generated UUID/object keys; never use `public/` or `storage:link` for payroll files.
- Protect downloads and previews with server-side policies, authenticated short-lived routes or tightly scoped signed URLs, ownership checks, and `Cache-Control: private, no-store`. A signed URL by itself is insufficient for payroll if shared without an identity check.
- Use encryption at rest for sensitive files where required by organizational policy: encrypted storage/object encryption or an application-encrypted blob with documented key management. Keep keys in supported secrets configuration, not source control. Treat the SMTP credential encryption already in use as a separate concern.
- **Never assume TCPDF's existing text-to-PDF generation can password-protect an uploaded PDF.** If uploaded-PDF encryption/password protection is requested, integrate a suitable PDF processing solution and **verify** generated output can be opened with the correct password and not without it. Otherwise clearly label protection unavailable and **block payroll** when the policy requires it. Do not use OCR/re-rendering to fake original-PDF preservation.
- Where password protection is used, do not email the password in the same message or expose it in CSV exports, application logs, or PDF metadata. Prefer organization-approved identity verification/employee portals when email attachments alone are not sufficiently safe.
- Limit file access by role and purpose: Super Admin retains configuration privilege; HR/payroll privilege must be **explicitly granted** and narrower than generic campaign access. Basic Admin, Supervisors, Agents, QAs, and unauthorized users must not read payroll campaigns, filenames, previews, or exports.
- Log actions (upload, validation, preview, approval, start, cancel, retry, purge) with actor ID, campaign ID, timestamp, safe aggregate counts and request identifiers; do not log document contents, SMTP secrets, salary amounts, download tokens, or whole email body.
- Configure retention, deletion scheduling, backups, temporary extraction cleanup, and expired download handling. Explain how personal data is removed from failed/cancelled campaigns too, consistent with authorized retention rules.
- Treat bulk payroll delivery as high risk. Use a visible confirmation gate before live sending; development/test never dispatches real emails.

## 6. Data design and architecture

Prefer cohesive services over expanding the existing large controller. Suggested structure (adapt to the project after investigation):

- Additive migrations for `email_campaign_attachments` (shared) and `email_campaign_recipient_attachments` or a unified `email_campaign_documents` table (mapped individual PDFs), plus purpose/mode and immutable manifest metadata on `email_campaigns`.
- Model associations on `EmailCampaign`, `EmailCampaignRecipient`, and attachments; named enums/constants for purpose, mode, status, and attachment verification state where appropriate.
- `EmailRecipientCsvImporter`: parsing, explicit mapping rules, row-numbered validation errors.
- `EmailCampaignAttachmentService`: ZIP safety, storage, hashing, virus-scanner interface, lifecycle.
- `EmailCampaignPreflightService`: readiness, counts, PDF/recipient pairing, queue/SMTP configuration and audit evidence.
- `EmailCampaignDeliveryService`: immutable recipient snapshot, mail composition, exactly one recipient address, intended attachments only.
- `EmailCampaignStatusService` or query layer: per-recipient state transitions and consistent aggregated counts.
- Form Request validation and policies/gates for each upload, preview, download, requeue, start, and cancel route.

Use unique indices/constraints on mapping keys and campaign recipient identity where possible. Add foreign keys to prevent cross-campaign attachment references. Immutable snapshots should include effective template/subject, sender, recipient email, intended attachment IDs/hash and purpose at the time of queueing; modifying a live template or swapping a file must not change an in-progress campaign. Jobs should serialize **IDs only**, never entire PDF bytes or secrets.

Be careful with large ZIP uploads: stream to private storage and avoid holding all PDFs in memory simultaneously. Eager-load only needed metadata and fetch the current recipient's attachment stream; release file handles after send. Do not introduce N+1 DB lookups per attachment. Use database transactions for status/manifest changes, not for long-running network sends.

## 7. Campaign creation, preflight, and UX

Maintain the existing Blade/Alpine visual language and accessible form patterns. Prefer an understandable flow rather than a cluttered page:

1. **Campaign details:** name, type/purpose, message template, sender account.
2. **Recipients & documents:** CSV template download, manifest import, private ZIP/multiple PDF upload, shared documents where permitted.
3. **Review mapping:** row-level errors; attachment count; missing/duplicate/extra files; masked sensitive identifiers; permitted preview controls.
4. **Delivery settings:** batch size, interval, provider rate limits, SMTP readiness, dedicated queue worker health.
5. **Confirm & send:** clear summary and explicit irreversible-send confirmation. No immediate send on upload; create a draft.
6. **Monitor:** live/polling status (lightweight, no excessive queries), paused/cancelled/blocked states, safe resend decisions, downloadable **redacted** status report.

Respect keyboard navigation, labels, help text, error focus, screen readers, responsive tables, progress feedback, clear empty/loading/error states, and current light/dark theme tokens. Avoid unnecessarily exposing salary file names or email addresses in toast messages. Distinguish:

- **Queued**: accepted for eventual processing, not yet submitted to SMTP.
- **Sending**: worker has claimed delivery.
- **Accepted**: SMTP/provider accepted the message (not confirmed in the inbox).
- **Delivered/Bounced**: only if verified by trustworthy provider event callbacks.
- **Blocked/Failed/Uncertain**: actionable but never blindly duplicated.

Add a **Send test to myself** option that uses **synthetic sample content or a specifically authorized preview** and is clearly separated from live sends. Do not let a generic test-email button accidentally attach another employee's payslip.

## 8. Queue reliability: fix "queued forever" without unsafe shortcuts

Investigate actual local code/deploy first: queue connection, dedicated worker process, Supervisor/systemd, worker logs, failed-jobs table, dispatch transaction, scheduled delays, DB/Redis visibility timeout, SMTP connectivity, and provider limits.

Current documented worker command:

```bash
php artisan queue:work --queue=email-campaigns --tries=1 --timeout=900
```

Production must run this worker under a supervisor/process manager with auto-restart and alerting. If sharing a worker among queues, its `--queue` list must **include `email-campaigns`** with an explicit priority decision; preserve telephony performance by preferring separate workers. Keep retry-after/visibility timeouts greater than actual job timeout and use a bounded worker restart policy. Confirm no accidental synchronous blast occurs in requests.

- Replace fragile global offset-based scheduling if needed with stable **recipient IDs/chunk IDs** and immutable delivery snapshots; avoid an import/change altering which file a batch picks up.
- Make per-recipient claiming atomic so two overlapping workers cannot intentionally send the same pending row concurrently.
- Account for crashes between SMTP acceptance and DB commit: **true exactly-once SMTP delivery cannot be guaranteed** without provider-supported idempotency. Preserve an `uncertain` status and reconciliation process; don't blindly retry uncertain deliveries.
- Allow bounded retries for known transient transport failures with exponential backoff and provider-aware rate limits; do not retry validation/mapping/security failures.
- Emit sanitized operational logs and metrics: oldest queued age, active worker heartbeat, success/failure counts, next scheduled batch, rate-limited count, mail transport errors with no secrets.
- Make cancel stop *unclaimed* recipients; document that an in-flight request might complete. Keep status totals accurate, especially on worker timeout, failure, retry and cancellation.
- Test a short one-recipient and two-recipient campaign locally with fake transports and a real **local non-sending queue worker** where possible; verify jobs actually leave the queue. Do not claim a 1-second SLA for message arrival.

## 9. Message composition and policy separation

- Send one MIME message per recipient, `to()` containing exactly one intended address, **never CC/BCC**. Verify the recipient's campaign and attachment ownership again at the point of send.
- For **marketing** campaigns, respect the existing `EmailOptOut` behavior, unsubscribe link, and opt-out checks at import **and** dispatch; preserve existing regression tests.
- For **payroll and other necessary transactional messages**, do not prepend "you subscribed to updates," do not use marketing unsubscribe logic as a blanket suppression policy, and do not create misleading compliance claims. Provide truthful sender identity and support address. Apply organization policy for mandatory blocking, employee email verification, and security review.
- Do not accept HTML from untrusted CSV columns; escape per-recipient variables, enforce a known variable allowlist, and preserve safe template rendering.
- The attachment sent must be the **exact intended document version**: verify hash/manifest reference at the time of send; if changed/missing, block with a safe error. Never substitute a different PDF.
- Preserve current generated PDF template behavior as an optional existing mode, but make uploaded PDFs a separate explicit feature. Never make converting scans to text a prerequisite for mailing a PDF.

## 10. Implementation plan (finish each phase with tests)

### Phase 1 — Baseline and design

- Inspect working tree, routes, policies, current UI, tests, docs, migrations, `AGENTS.md` if present, queue configuration and deployment commands.
- Reproduce "campaign remains queued" using synthetic recipients, identify whether the dedicated queue is running, and document actual blocker.
- Write a small technical design and migration checklist. Keep unrelated business logic untouched.

### Phase 2 — Secure document ingestion and immutable mapping

- Add additive database schema, private storage, ZIP/multiple-PDF handling, CSV manifest mapping, server-side policies, retention, and tests for reject/fail-closed paths.
- Provide both downloadable CSV templates. Preserve old `email,name` import and generated-PDF workflows.
- Build an explicit, testable per-recipient manifest snapshot.

### Phase 3 — Sending and reliability

- Integrate mapped file attachments into queued delivery while retaining one recipient per send.
- Separate marketing and transactional content/opt-out logic.
- Add preflight gate, atomic claim, safe cancellation and retry/reconciliation, worker diagnostics, and sanitized audit logs.

### Phase 4 — UI and documentation

- Improve the existing Super Admin panel with clear delivery modes, upload status, mapping review, preview, send confirmation and progress views.
- Update `docs/EMAIL_CAMPAIGNS.md`, queue worker instructions, `.env.example` only for required new settings, private storage/retention procedures, and deployment notes.
- Document user-facing payroll CSV+ZIP example and operator security checklist.

### Phase 5 — QA and rollout

- Add tests listed below; run targeted tests first, then wider relevant suites, Pint, and build checks.
- Verify additive migration + rollback strategy against an isolated copy with synthetic data. Preserve existing campaigns and statuses.
- Include deployment order for migrations, service restarts, queue workers and retention jobs. Enable new live behavior only after preflight and supervised verification.

## 11. Required automated tests and acceptance criteria

**Must have feature/unit tests with fake mail transport, fake filesystem, fake queue, and synthetic test PDFs:**

1. Campaign A with Alice's `alice.pdf` and Bob's `bob.pdf` sends two individual MIME messages; Alice's attachment SHA matches **only** `alice.pdf`, Bob's matches **only** `bob.pdf`; no accidental shared personal PDF, no CC/BCC.
2. Campaign using shared `policy.pdf` attaches the same intended PDF to each recipient and never attaches unrelated private docs.
3. Mixed mode includes only explicitly shared + correct personal documents; old generated-PDF functionality remains compatible.
4. Missing, duplicated, unreadable, modified, corrupt, or mismatched recipient PDFs **block send** before dispatch; failed preflight is visible and no email is sent.
5. ZIP traversal/symlink/oversized/extraction bomb/duplicate-path attempts are rejected; temporary files cleaned and private paths not exposed.
6. Unauthorized roles cannot upload, view filenames, preview/download docs, start, resend, or export payroll recipient details—even by guessing IDs or signed links.
7. Email/filename mismatches, duplicate addresses, CSV BOM/case/header variations, empty rows, invalid MIME, unexpected files, and CSV formula injection are handled safely.
8. Marketing opt-outs stay respected. Payroll messages do not include promotional "subscribed" footers and are routed according to organization-defined transactional policy.
9. Queued campaigns are processed only by the dedicated consumer. Simulate an interrupted worker and confirm recovery statuses without blind duplicate sends.
10. Transport failures, cancellation, retries, concurrency and idempotent claims keep recipient and aggregate counts consistent.
11. A draft may be edited; a queued/sending campaign cannot change who receives which document. No cross-campaign attachment read.
12. Access logs, error output, previews and CSV status exports contain no payroll PDF bytes, secret passwords, salary amounts, or public document URLs.
13. Existing `tests/Feature/Admin/EmailCampaignsTest.php` and `tests/Feature/Admin/EmailConfigurationTest.php` still pass and do not gain flaky dependencies on real SMTP, Redis, OCR installations or external providers.

Useful checks (adapt to project tooling and environment):

```bash
php artisan test tests/Feature/Admin/EmailCampaignsTest.php tests/Feature/Admin/EmailConfigurationTest.php
php artisan test --filter=EmailCampaign
vendor/bin/pint --test
php artisan route:list --name=email-campaigns
php artisan queue:failed
```

Do not claim tests passed if they were not actually run. Record commands, outputs, warnings and environmental blockers.

## 12. Quality and safety constraints

- Laravel 12 / PHP requirements are in the inspected repository; respect `composer.lock` and deployed PHP compatibility instead of upgrading major dependencies reflexively.
- Match existing Blade/Alpine components, permissions, route names, Tailwind design tokens, and service conventions; use transactions for short DB state changes and streaming IO for large files.
- Never expose `.env`, SMTP passwords, real payroll files, personal employee data, or secret keys in git commits/tests/PRs.
- Do not download unreviewed PDF tools, add heavy libraries, use external OCR APIs for payroll, or make new provider integrations without a justified, documented reason. Review dependency licenses and security updates.
- Do not refactor telephony, VICIdial, CRM reporting, or unrelated admin code as a side effect.
- Prefer accurate, specific errors ("Missing PDF for 2 recipients") without revealing PII. Maintain consistent naming, readable services and tests.
- Minimize unnecessary stored personal data and define an authorized data-retention/incident-response process. Follow applicable organizational HR/privacy policies and legal requirements; do not make unsupported compliance claims.

## 13. Definition of done and reporting contract

A change is complete only when authorized staff can create **a draft**, upload a CSV plus different PDFs, inspect and verify every mapping, explicitly approve a live send, and have a running queue worker send the **correct PDF to each corresponding recipient**. Shared attachments and existing templates still work; campaign results are truthful and auditable; invalid mappings block delivery; private documents cannot be accessed by other roles; automated regression tests pass; and the deployment/runbook is current.

Every agent response that implements changes should finish in this format:

```text
RESULT
- What is completed and what remains.

CHANGES
- Concrete files/migrations/routes/UI/workers touched.

VALIDATION
- Commands executed and pass/fail evidence; include one-to-one attachment safety checks.

SECURITY
- Authorization, private storage, mapping integrity, confidentiality and retention review.

BLOCKERS
- Remaining issues, unverified operational assumptions, safe next steps.
```

**Priority order:** (1) no cross-recipient leakage, (2) clear authorization, (3) reliable queuing and delivery-state accuracy, (4) operator-friendly UX, (5) maintainability and performance.

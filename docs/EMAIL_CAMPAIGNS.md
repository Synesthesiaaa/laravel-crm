# Super Admin Email Campaigns and Private PDF Delivery

> Current operator and deployment guide, checked against repository source on 2026-10-15.
> [EMAIL.md](../EMAIL.md) preserves historical implementation requirements; this guide describes current behavior.
> Commands below are instructions for an authorized operator, not actions performed during this documentation update.

## Access and safe preparation

Only **Super Admin** can use `/admin/email-campaigns`, `/admin/email-configuration`, templates, imports, review, private downloads, reports and send controls. Admin, Team Leader and Agent roles receive no access. There is no HR role exception. The public marketing unsubscribe page is a separate signed-link flow. See [admin routes](../routes/admin.php), [campaign policy](../app/Policies/EmailCampaignPolicy.php) and [public routes](../routes/web.php).

Prepare an authorized recipient list, verified document ownership, an approved sender/provider and a supervised email consumer before live sending. Use synthetic documents and reserved `example.com` addresses for preparation examples; do not queue these examples to a live provider. The application verifies declared mappings and integrity; it cannot establish that PDF contents belong to the named employee or that organizational permission exists.

## Configure and test SMTP

Open **Super Admin → Email Configuration**. Save an enabled connection with provider-approved host, port, security, optional username/password, From name and From address. Database settings take precedence over the environment mailer while enabled. The page reports its active source and readiness; readiness is configuration detection, not a successful network/authentication check.

| Field | Current server validation / behavior |
| --- | --- |
| Enabled | Required boolean. Disabling restores environment fallback; it is not a global emergency stop. |
| Host | Required, at most 255 characters; letters, digits, dot, hyphen, brackets and colon only. No URL scheme, path or embedded credentials. |
| Port | Integer 1–65,535; obtain the correct value from the provider. |
| Security | `tls` requires STARTTLS; `ssl` uses implicit SMTPS; `none` disables automatic TLS. Use the provider's approved secure setting. |
| Username | Optional, at most 255 characters. Clearing it also clears the saved password. |
| Password | Optional, at most 2,048 characters. Blank preserves an existing password when username remains populated. Stored encrypted and hidden after saving. |
| From name / address | Required; each at most 255 characters; address must pass RFC email validation. Provider sender/domain approval remains external. |

The configuration screen's **test email** sends a real message immediately to the entered recipient (not necessarily yourself). Confirm that address before requesting it. It does not use the campaign worker or live-bulk opt-in flags. Campaign review's **Send synthetic test to myself** instead sends synthetic content only to the signed-in operator's account email, with no campaign data or attachments. Both test endpoints are throttled to three requests per minute. A successful test means transport acceptance, not delivery to an inbox.

If no enabled database connection exists, [EmailSmtpService](../app/Services/EmailSmtpService.php) recognizes environment mailers `smtp`, `ses`, `ses-v2`, `postmark`, `resend` and `sendmail`; `log` (the repository default), `array`, `failover` and `roundrobin` are not considered ready. For environment SMTP, [mail configuration](../config/mail.php) reads `MAIL_MAILER`, `MAIL_SCHEME`, `MAIL_URL`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_EHLO_DOMAIN`, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`. Do not assume a legacy `MAIL_ENCRYPTION` value controls this configuration.

Enabled database SMTP uses a 15-second transport timeout. Environment SMTP has a null timeout in the checked-in configuration; do not assume the database timeout applies to it. Already frozen campaigns keep their approved encrypted transport and sender snapshot, including the then-current credentials. Updating or disabling saved SMTP does not rewrite those campaigns; retry requires the approved manifest/settings to match again. Configuration tests use current settings. See [configuration controller](../app/Http/Controllers/Admin/EmailConfigurationController.php), [preflight](../app/Services/EmailCampaignPreflightService.php) and [delivery](../app/Services/EmailCampaignDeliveryService.php).

## Templates, generated PDFs and OCR

Create a reusable template with name (up to 150 characters), subject (up to 255) and message (up to 50,000). Despite the stored field name `html_body`, the message is escaped plain text with preserved line breaks; HTML markup is not rendered. Supported campaign variables are `{{name}}`, `{{email}}`, `{{employee_id}}` and `{{period}}`; missing non-name values or unknown variables block preflight. Blank names become `Recipient`.

The optional generated PDF has separate plain-text PDF content (up to 50,000 characters) and a password of 8–128 characters. Its password is encrypted at rest. TCPDF generates an AES-256 protected A4 document; distribute the password separately through an approved channel. This is one template password, not an employee-specific password derivation feature. A blank password on editing retains the old password; disabling the generated PDF clears its content/password. Templates referenced by queued, sending or completed campaigns cannot be edited; create another template. Frozen snapshots protect already-approved campaigns even where other statuses permit template editing.

Template PDF preview uses sample name/email only. It does not validate a complete recipient manifest or substitute employee/period data as the final campaign preflight does. Generated PDFs are additional attachments and do not encrypt uploaded originals.

**Import document** extracts text into a new editable template; it does not attach the uploaded original. Accepted OCR inputs are PDF, PNG, JPG/JPEG and WebP up to 10 MiB. PDF extraction/OCR processes the first five pages; image OCR uses English data. Extraction is limited to 30,000 characters. Review names, amounts and wording manually before use; OCR can be inaccurate. OCR imports leave generated PDF disabled. Binaries are configured by `OCR_TESSERACT_BINARY`, `OCR_PDFTOTEXT_BINARY` and `OCR_PDFTOPPM_BINARY`. Attaching an original PDF uses the separate document-upload workflow and never requires OCR. See [template/import actions](../app/Http/Controllers/Admin/EmailCampaignsController.php) and [document service](../app/Services/EmailCampaignDocumentService.php).

## CSV contract and upload limits

Download templates from the campaign screen, or inspect the synthetic [generic example](examples/email-recipients.csv) and [personalized example](examples/email-personalized-recipients.csv). Personal examples name files only; no PDFs are bundled. They are not live-send lists.

| Import path | Required columns | Other behavior |
| --- | --- | --- |
| Marketing with upload mode `none` or `shared` | `email`; optional `name` | Legacy generic importer lowercases full addresses and deduplicates them (last row wins). Up to 20,000 unique addresses; physical-line guard 50,000. Extra columns are not retained. |
| Transactional without personal PDFs | `email` | Strict importer; only `email,name,employee_id,period,pdf_filename` headers allowed. Leave `pdf_filename` absent/blank. |
| Any purpose with personal PDFs | `email,pdf_filename` | Strict importer; every row maps to exactly one personal PDF. |
| Payroll | `email,pdf_filename,employee_id,period` | Strict importer, personal-PDF mode and payroll policy gates required. |

CSV upload is limited to 5 MiB (`csv`/`txt`). Strict imports default to 10,000 recipients (`EMAIL_MAX_RECIPIENTS`) and a 50,000 physical-line guard. All header names are trimmed/case-insensitive and UTF-8 BOM is accepted. Strict headers must be unique; rows must have the same number of fields as the header. Strict values must be valid UTF-8, at most 255 bytes, contain no control characters and not begin with `=`, `+`, `@` or `-` after trimming. Blank lines are skipped. Strict imports reject duplicate addresses including case collisions and duplicate case-insensitive filenames. They preserve the email local part and lowercase the domain. Payroll identifiers/periods are required strings; the importer does not verify an HR roster or enforce a specific period format. Do not assume legacy marketing imports provide all strict validation protections.

Choose an uploaded attachment mode explicitly: `none`, `shared`, `recipient_specific`, or `shared_and_recipient_specific`. Choose either a ZIP **or** multiple personal PDFs, not both. Shared PDFs must be separate uploads and require a shared mode. Uploaded filenames must match `pdf_filename` exactly including case; no directory paths, URLs, inferred names, ordered pairing, leading dots/formula characters, `..`, control characters or unsafe path characters. Filenames are at most 255 bytes with a `.pdf` suffix. Case-colliding names across shared and personal files are rejected. Extra and missing personal files block the entire draft creation; do not fix a mismatch by deleting an arbitrary row.

Default original-document limits are 10 MiB per file, 100 MiB combined uncompressed content, and 500 documents including shared files. ZIP upload size is also bounded by the total limit. ZIP entries must be root-level regular PDFs, stored or deflated, unencrypted, nonempty, with expansion ratio at most 200:1. Server/PHP request limits can reject smaller uploads first. The configurable limits are in [email_campaigns.php](../config/email_campaigns.php); [request validation](../app/Http/Requests/StoreEmailCampaignRequest.php), [strict importer](../app/Services/EmailRecipientCsvImporter.php), [attachment ingestion](../app/Services/EmailCampaignAttachmentService.php) and [PDF inspector](../app/Services/EmailPdfInspector.php) are authoritative.


The existing reusable templates, OCR imports, generated password-protected PDFs,
SMTP settings and generic `email,name` CSV imports remain supported. Uploaded
original PDFs are a separate workflow: OCR is never required for attaching them.
Every message has exactly one recipient and no CC/BCC.

## Purpose and document modes

- Marketing honors global email opt-outs at import and dispatch and retains the
  signed unsubscribe method. Generic marketing CSVs retain historical deduplication.
- Transactional notices use authorized recipient lists without promotional subscription
  wording or marketing unsubscribe headers. Organization-wide security blocks must
  be resolved before import; a marketing opt-out is not a transactional policy.
- Payroll requires `EMAIL_PAYROLL_ENABLED=true` after organizational approval and an
  individual PDF for every recipient with `employee_id` and `period` supplied.
  `EMAIL_PAYROLL_PASSWORD_REQUIRED=true` blocks payroll because uploaded original
  PDF password protection is unavailable. Do not disable a required policy to send.
- Attachment modes: none, shared, recipient-specific, shared plus recipient-specific.
  Intentionally shared PDFs go to every recipient. A template's generated PDF is
  additional and appears in the review. Generated PDFs use their existing password
  protection; uploaded PDFs do not gain password protection from the template.

## Private import and operator journey

1. Create a plain-text message template. Supported variables are `{{name}}`,
   `{{email}}`, `{{employee_id}}`, `{{period}}`. Unknown or missing required values
   block preflight; optional blank names use Recipient. No CSV HTML is rendered.
2. Choose purpose, uploaded attachment mode, template and pacing. The configured
   Email Configuration sender is shown during review and frozen at queue time.
3. Download the original CSV template for generic campaigns, or the personalized
   PDF template for individual files:

   ```csv
   email,name,employee_id,period,pdf_filename
   alice@example.com,Alice Example,EMP-1001,2026-09,EMP-1001-2026-09.pdf
   bob@example.com,Bob Example,EMP-1002,2026-09,EMP-1002-2026-09.pdf
   ```

   These are synthetic examples. Use only authorized recipients and documents.
   Sensitive manifests reject duplicate emails and case collisions, duplicate PDF
   mappings, malformed rows and formula-like values. Email local parts are preserved;
   only the domain is lowercased. UTF-8 BOM and case-varied headers are supported.
4. Upload a ZIP containing uniquely named PDFs at its root, or multiple personal PDFs.
   Each `pdf_filename` must match the uploaded name exactly. No subdirectories,
   filename inference, employee-name matching, URL/path references or ordered pairing.
   Missing and extra personal files block the entire import. For marketing personal
   PDFs, opted-out rows block import instead of silently altering the mapping.
5. Add shared PDFs only when every listed recipient is authorized to receive them.
   Original attachment uploads are PDF-only, and encrypted/password-protected uploads are rejected.
   Defaults: 10 MiB per PDF, 100 MiB total, 500 files, 10,000 strict-manifest recipients.
   ZIP traversal, symlinks, encryption, duplicate/case-colliding paths, unsupported
   compression, nested archives and excessive expansion are rejected before extraction.
6. Create a draft. Non-marketing purposes and any uploaded attachment mode require the dedicated review/dry-run/confirmation flow. Marketing with attachment mode `none` uses the simpler index Start sending path, even if its template generates a PDF. Review every recipient and PDF; authenticated previews are private
   no-store downloads. Sensitive identifiers are partially masked. Edit draft details
   before sending; changes invalidate the dry-run. Correct mapping/files through a
   replacement draft; queued mappings cannot be edited.
7. Run internal dry-run. This verifies mappings, content and hashes without SMTP.
   The separate Send synthetic test to myself action sends only synthetic text to the
   current operator's account address, with no personal documents or employee PDFs.
   This is an explicitly requested real SMTP test and works locally with configured
   SMTP even when live bulk sending is disabled. It does not need the email queue
   worker. SMTP/account/transport errors appear in a separate synthetic-test alert
   and do not invalidate the campaign's document mapping or require a replacement draft.
8. Explicitly confirm the mapping, sender, purpose and live send. Preflight runs again
   and freezes encrypted message, sender/SMTP settings, recipient identities and
   intended document IDs/hashes. Live sending is disabled by default and always blocked
   in testing HTTP environments outside synthetic test harnesses. Local bulk sending
   requires both `EMAIL_CAMPAIGN_LIVE_ENABLED=true` and `EMAIL_CAMPAIGN_ALLOW_LOCAL=true`.
   These opt-ins do not bypass SMTP, dedicated worker, payroll policy, document
   integrity, dry-run or confirmation checks; the worker uses the same live policy.
9. Refresh review for per-recipient outcomes. Accepted means provider/transport accepted,
   never confirmed inbox delivery. Delivered/bounced provider events are not implemented.
   The redacted CSV contains recipient IDs, allowlisted states and acceptance timestamps;
   it excludes addresses, filenames, personal identifiers and PDF contents.

## Deployment and queue reliability

Deploy the additive `2026_10_13_100000_add_secure_email_documents.php` migration
before serving new code. It retains historical campaigns and statuses. Rehearse
migration/rollback against an isolated synthetic database. Rollback before creating
confidential campaigns; rollback removes new document metadata, so retention cleanup
must run before any later approved rollback. Never delete historical campaign tables.

Install the organization's approved Poppler `pdfinfo` binary on web and worker hosts.
Configure PHP `upload_tmp_dir` to an access-restricted private directory; configure
upload/post size limits for the authorized archive limit. Import and MIME workspaces
use restrictive private UUID directories and are cleaned on success/failure, with
scheduled abandoned-copy recovery after process interruption.
Set `EMAIL_PDFINFO_BINARY` to its executable path. Original uploads require successful
structural inspection with pages and no encryption; unavailable verification fails
closed. OCR separately uses Tesseract and Poppler pdftotext/pdftoppm, as before.
An optional approved ClamAV-compatible executable can be supplied through
`EMAIL_DOCUMENT_SCANNER`; it receives `--no-summary <private-file>`. Set
`EMAIL_DOCUMENT_SCAN_REQUIRED=true` when policy mandates scanning; missing/failed
scanning blocks import. Do not download unreviewed tools or use external OCR for payroll.

New email jobs use a dedicated connection, independent of telephony/default:

```dotenv
EMAIL_CAMPAIGN_CONNECTION=email-database
EMAIL_CAMPAIGN_QUEUE=email-campaigns
EMAIL_CAMPAIGN_LIVE_ENABLED=false
EMAIL_CAMPAIGN_ALLOW_LOCAL=false
EMAIL_PAYROLL_ENABLED=false
EMAIL_PAYROLL_PASSWORD_REQUIRED=true
EMAIL_DOCUMENT_RETENTION_DAYS=7
```

Production can use `email-redis` after configuring its Redis backend; both dedicated
connections have a 120-second visibility timeout, above the 60-second recipient job
limit. New batches only distribute recipient jobs; database-configured SMTP attempts use a 15-second timeout.
Start ONE supervised worker on exactly the configured connection/queue:

```bash
php artisan queue:work email-database --queue=email-campaigns --sleep=1 --tries=1 --timeout=60 --max-time=3600
```

The Linux example is `deploy/supervisor/laravel-crm-email.conf.example`. Adjust paths,
service identity and selected email connection, then reread/update Supervisor and
restart workers after deployment. `composer dev:email` starts the dedicated local
worker explicitly; `composer dev` preserves separate telephony/default processing.
A recent consumer heartbeat is required by production preflight. Cache must be shared
between web and worker. A one-second delay neither starts a worker nor guarantees
one-second arrival. Keep provider quotas in the campaign batch/delay settings.

### Production uses Horizon but email heartbeat is missing

Horizon consumes Redis queues. An application configured with
`EMAIL_CAMPAIGN_CONNECTION=email-database` needs a separate database queue worker;
adding `email-campaigns` to a Redis supervisor does not consume database jobs.
Keep the configured backend when introducing its consumer.

For an Ubuntu deployment at `/var/www/laravel-crm`, verify the PHP-FPM/application
service user and review the supplied Supervisor example (`www-data` is an example,
not a verified service identity). The selected user must read the application/.env
and access Laravel storage, the database and the same Redis cache used by web requests.

```bash
cd /var/www/laravel-crm
ps -eo user,args | grep '[p]hp-fpm: pool'
cp -n deploy/supervisor/laravel-crm-email.conf.example /etc/supervisor/conf.d/laravel-crm-email.conf
nano /etc/supervisor/conf.d/laravel-crm-email.conf
```

Review directory, PHP executable (`/usr/bin/php8.5` if that is the deployed CLI),
`user`, `email-database`, and `email-campaigns`; retain one process and auto-restart.
If the destination already exists, inspect it rather than overwriting it blindly.
After reviewing queued campaigns, apply only this Supervisor group:

```bash
supervisorctl reread
supervisorctl update laravel-crm-email
supervisorctl status laravel-crm-email
php artisan tinker --execute='dump(app(\App\Services\EmailCampaignQueueStatus::class)->inspect());'
```

Expect the service to be RUNNING and `problem` to be null, then refresh the email
screen. A started worker can process already-approved pending campaigns. If it exits,
inspect the dedicated worker log and its runtime-user permissions; do not fabricate
a heartbeat. This does not require restarting unrelated Horizon/Reverb/telephony
processes. Cache-store/prefix mismatch and old cached code/config are secondary
checks if a confirmed running matching consumer still has no visible heartbeat.

Do not switch queue backends while historical jobs remain: drain/reconcile the old
connection first. Legacy queued batches are supported but should be drained before
new live rollout. Restart old workers before they can consume new campaign jobs.
Set `APP_URL` to the public HTTPS CRM URL for signed marketing unsubscribe links.
After synthetic rehearsal, approve configuration, set `EMAIL_CAMPAIGN_LIVE_ENABLED=true`
in the intended environment, and supervise the initial live operation. For explicitly
authorized local bulk delivery, additionally set `EMAIL_CAMPAIGN_ALLOW_LOCAL=true`.
Keep APP_ENV=local; do not disguise development as production. Clear cached config
with `php artisan config:clear`, then restart only the dedicated email worker to load
the settings. Start/cancel/retry controls and one-recipient document safeguards still
apply. Turning either required flag off and restarting the worker disables local
live transport again. No worker start or campaign dispatch is implied by setting flags.

## Interruption, cancellation and reconciliation

Atomic campaign/recipient locks prevent duplicate claims. Cancellation stops unclaimed
recipients; in-flight transport may finish. Re-running a job cannot resend an accepted,
sending, skipped, cancelled or uncertain recipient. Stale sending claims become uncertain.
SMTP cannot guarantee exactly-once submission across acceptance and DB persistence.
Transport exceptions are conservatively uncertain and never automatically retried;
exception text is not logged because it can expose recipient/SMTP information.

Only blocked PRE-SEND recipients can be requeued through an expressly confirmed retry,
after original document integrity and approved settings have been restored and
preflight rerun. Uncertain recipients remain excluded. Compare provider audit evidence
before recording any reconciliation; absence of evidence is not permission to resend.
For uncertain rows, **Record provider evidence** requires explicit confirmation and
a provider case ID containing no personal data. Record verified acceptance with the
provider's acceptance time, or a verified no-submission finding. The latter becomes
blocked and still requires separate revalidation and explicit retry authorization.
Reconciliation is audited and never sends a message. There is no automatic provider
callback or direct uncertain-resend endpoint in this release.
Pending jobs lost before claim can be recovered by an authorized server operator:

```bash
php artisan email-campaigns:maintain --recover
php artisan queue:failed
```

Recovery dispatches pending IDs only. Do not blindly retry legacy failed queue payloads.
Monitor heartbeat, oldest queued campaign age, failed jobs and provider quotas. The
admin screen reports queue connectivity/missing consumer issues; it does not certify
future worker uptime. Existing local Reverb/SIP failures are separate from email status.

## Confidentiality and retention

`email_documents` is a dedicated non-serving private disk. Files use random UUID keys
and Laravel authenticated encryption with APP_KEY. SHA-256 hashes validate the original
bytes on preview and immediately before transport. A composite foreign key binds
personal attachments to both campaign and recipient; send rechecks ownership and the
frozen document version. All routes remain Super Admin only; no new HR grants are
invented. Generic Admin, Team Leader and Agent cannot read/upload/preview/export/start.

Scheduled `email-campaigns:maintain` runs every five minutes. Enable Laravel's scheduler
and monitor it. It purges expired originals for drafts, failed and cancelled campaigns,
records purged metadata, removes abandoned temporary extraction copies and orphaned
encrypted blobs, and reconciles stale claims. Current sending recipients defer purge
until their transport boundary is resolved. Expired/missing documents block previews
and sends. Audit entries include actor/campaign/document IDs and safe action/counts;
no salaries, PDF bytes, passwords, download tokens or complete messages are logged.

APP_KEY backups and approved key rotation procedures are required: lost keys make
retained files/snapshots unreadable. File deletion is logical deletion, not a claim of
physical erasure from SSDs, filesystem snapshots or backups. Apply organizational
backup expiry, HR retention, access review and incident-response procedures as well.
SMTP credential encryption is separate from original-document protection.

## Validation and known limitations

```bash
php artisan test tests/Feature/Admin/EmailCampaignsTest.php tests/Feature/Admin/EmailConfigurationTest.php tests/Feature/Admin/EmailDocumentDeliveryTest.php
php artisan route:list --name=email-campaigns
npm run build
```

Tests use synthetic TCPDF documents, fake disks/queues and array transports. Ingestion
mapping tests substitute the PDF inspector; real inspector tests verify fail-closed
behavior for non-PDF/unavailable verification. Positive real Poppler inspection and
mandated scanner/password policies must be validated on the intended deployment.
No actual payroll documents, production mail blasts or SMTP delivery claims belong in
these development tests. Operational dry-run validates content/integrity; it does not
prove SMTP connectivity, inbox delivery, malware-free PDFs or organizational approval.


## Progress and safe incident handling

Refresh the review screen to update per-recipient counts (50 rows per page). Campaign history is paginated at 15 campaigns. The index warning flags queued/sending campaigns older than two minutes with no accepted, failed or skipped totals; it is a diagnostic cue, not proof of a stopped worker. Queue pending count measures jobs, not recipients: batch jobs can distribute recipient jobs. Batch size is 1–100 and delay is 1–3,600 seconds. Delays set job eligibility; SMTP latency, backlog and provider quotas still determine throughput. Keep one consumer for the documented pacing assumptions.

| Recipient state | Meaning and permitted next action |
| --- | --- |
| `pending` | Not claimed; may wait for delay/worker or become cancelled. |
| `sending` | Claimed; transport may be in progress. Do not resend. |
| `accepted` | Frozen campaign transport returned success. Acceptance is not inbox delivery. |
| `sent` | Historical acceptance label; also not proof of delivery. |
| `skipped` | Marketing address opted out before dispatch. |
| `blocked` | Verification failed before entering transport. Fix the cause; explicit verified retry may be available. |
| `uncertain` | Transport boundary was entered or worker interrupted. Provider evidence is required before any resend decision. |
| `cancelled` | Unclaimed recipient cancelled in a frozen campaign. |
| `failed` | Historical/compatibility failure state; it is not selected by the new blocked-only retry action. |

Campaigns move from `draft` to `queued`/`sending`, then `completed` when no pending/sending rows remain, or `needs_attention` if blocked/uncertain rows remain. Cancel is supported for draft/queued/sending campaigns. `completed` can include skipped recipients and is not a delivery certification. Totals labeled sent aggregate accepted + historical sent; failed aggregates failed + blocked + uncertain. See [delivery state transitions](../app/Services/EmailCampaignDeliveryService.php), [recipient job](../app/Jobs/SendEmailCampaignRecipient.php) and [batch job](../app/Jobs/SendEmailCampaignBatch.php).

**Redacted status CSV** exports `recipient_id,status,provider_accepted_at`. It excludes addresses, filenames and identifiers; internal recipient IDs still deserve controlled handling. Historical `sent` timestamps appear in the same acceptance column. There is no delivered/opened/bounced tracker or provider webhook implementation.

| Symptom | Check and safe response |
| --- | --- |
| No recent heartbeat | Verify exact connection **and** queue, running consumer, shared cache store/prefix and deployed code/config. The matching worker refreshes a cache heartbeat with 90-second lifetime. A reachable database/Redis queue alone is insufficient. |
| Queued but no progress | Inspect dedicated worker log, eligibility delays, provider limits and pending/failed jobs. Starting a worker can process approved queued campaigns. Do not repeatedly click Start or indiscriminately retry queue payloads. |
| Synthetic test fails | Verify current SMTP source, sender approval, account email for self-test, provider credentials/security, DNS/network access. It does not require replacing a valid manifest or rerunning document imports. |
| Dry-run or queue confirmation rejected | Read preflight errors; check payroll policy, exact recipient/PDF mapping, required variables, retention and hashes. Editing draft details clears dry-run approval. Changes to current sender/transport/template can also make the manifest hash differ. Repeat verification before confirming. |
| Blocked after queueing | Current live/payroll/scanner policy, expired/missing/modified documents or frozen ownership may block transport. Restore approved conditions, then use the blocked-only retry control if the campaign is `needs_attention`. |
| Uncertain outcome | Obtain provider evidence; record accepted or verified no-submission through the review form. Do not infer no-submission from an empty inbox or a missing application timestamp. |
| Expired/purged PDF | Preview/send fails closed. A replacement authorized draft and fresh originals may be needed. Recreating a campaign is not permission to resend a previous uncertain submission. |

For reconciliation, provider case ID is required (up to 64 ASCII letters/digits/underscore/hyphen, no personal data). Accepted outcomes require a valid timestamp no later than now; the form labels the application timezone. A verified no-submission outcome becomes blocked and still needs separate preflight and explicit retry. Cancelled campaigns have no resume action. Retrying applies only to `needs_attention` campaigns with a snapshot and `blocked` recipients; accepted, sending, skipped, cancelled and uncertain rows are excluded. These controls and the export contract are implemented in the [campaign controller](../app/Http/Controllers/Admin/EmailCampaignsController.php).

### Retention boundaries

Expiry starts at upload, with a minimum of one day even if a smaller configured retention value is supplied. Maintenance considers expired originals across all campaign statuses, including completed campaigns; it defers a campaign's purge while any recipient is `sending`. Stale claims older than three minutes become uncertain. Orphan encrypted blobs and abandoned private UUID workspaces older than one hour are eligible for cleanup. `--recover` redispatches only pending recipients in queued/sending campaigns with a frozen manifest; it also performs the normal purge/reconciliation maintenance and is not a read-only command.

Maintenance deletes original encrypted blobs and marks document metadata purged. It does **not** implement deletion of campaign history, recipient addresses/employee metadata, encrypted message/SMTP snapshots, template passwords, activity records, provider copies or backups. Set separate approved retention/access procedures for these records. See [maintenance command](../app/Console/Commands/MaintainEmailCampaigns.php), [scheduler](../routes/console.php), [private disk](../config/filesystems.php) and [secure-document schema](../database/migrations/2026_10_13_100000_add_secure_email_documents.php).

## Source and verification map

The [processing diagram](diagrams/email-campaign-processing.md) includes an equivalent text description. [Admin campaign entry](admin/email-campaigns.md), [configuration entry](admin/email-configuration.md) and [SMTP integration entry](integrations/smtp.md) point here to keep operating instructions in one place.

Repository tests describe expected behavior; this documentation update did not execute them, contact SMTP, start consumers, run maintenance, migrate a database or inspect live data. Candidate checks for an approved isolated synthetic environment include the four focused suites below; review the test environment first because they use database-refresh fixtures.

- [EmailCampaignsTest](../tests/Feature/Admin/EmailCampaignsTest.php): access, templates, OCR, generic import, queue dispatch, cancellation and signed unsubscribe.
- [EmailConfigurationTest](../tests/Feature/Admin/EmailConfigurationTest.php): configuration validation, encryption/security selection, fallback and transport sender.
- [EmailDocumentDeliveryTest](../tests/Feature/Admin/EmailDocumentDeliveryTest.php): private mapping, snapshots, archive rejection, integrity, retention, synthetic self-test and evidence reconciliation.
- [EmailCampaignLocalSendingTest](../tests/Feature/Admin/EmailCampaignLocalSendingTest.php): local opt-ins, worker policy, readiness and retry.

```bash
php artisan test tests/Feature/Admin/EmailCampaignsTest.php tests/Feature/Admin/EmailConfigurationTest.php tests/Feature/Admin/EmailDocumentDeliveryTest.php tests/Feature/Admin/EmailCampaignLocalSendingTest.php
```

No positive production Poppler/scanner validation, real SMTP/inbox delivery, provider quota verification or browser workflow result is claimed by this guide. Neither a synthetic SMTP test nor an internal dry-run certifies malware freedom, confidential document ownership or HR authorization.

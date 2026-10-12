# Super Admin Email Campaigns and Private PDF Delivery

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
   Initial uploads are PDF-only, and encrypted/password-protected uploads are rejected.
   Defaults: 10 MB per PDF, 100 MB total, 500 files, 10,000 strict-manifest recipients.
   ZIP traversal, symlinks, encryption, duplicate/case-colliding paths, unsupported
   compression, nested archives and excessive expansion are rejected before extraction.
6. Create a draft. Review every recipient and PDF; authenticated previews are private
   no-store downloads. Sensitive identifiers are partially masked. Edit draft details
   before sending; changes invalidate the dry-run. Correct mapping/files through a
   replacement draft; queued mappings cannot be edited.
7. Run internal dry-run. This verifies mappings, content and hashes without SMTP.
   The separate Send synthetic test to myself action sends only synthetic text to the
   current operator's account address, with no personal documents or employee PDFs.
8. Explicitly confirm the mapping, sender, purpose and live send. Preflight runs again
   and freezes encrypted message, sender/SMTP settings, recipient identities and
   intended document IDs/hashes. Live sending is disabled by default and always blocked
   in local/testing HTTP environments outside synthetic test harnesses.
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
EMAIL_PAYROLL_ENABLED=false
EMAIL_PAYROLL_PASSWORD_REQUIRED=true
EMAIL_DOCUMENT_RETENTION_DAYS=7
```

Production can use `email-redis` after configuring its Redis backend; both dedicated
connections have a 120-second visibility timeout, above the 60-second recipient job
limit. New batches only distribute recipient jobs; SMTP attempts use a 15-second timeout.
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

Do not switch queue backends while historical jobs remain: drain/reconcile the old
connection first. Legacy queued batches are supported but should be drained before
new live rollout. Restart old workers before they can consume new campaign jobs.
Set `APP_URL` to the public HTTPS CRM URL for signed marketing unsubscribe links.
After synthetic rehearsal, approve configuration, set `EMAIL_CAMPAIGN_LIVE_ENABLED=true`
in the production environment only, and supervise the initial live operation.

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

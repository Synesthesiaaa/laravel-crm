# Super Admin Email Campaigns

The **Super Admin → Email Campaigns** screen supports reusable message templates,
optional password-protected PDF attachments, image/scanned-PDF OCR imports,
recipient CSVs, and queued deliveries in batches.

## Setup

1. Install PHP dependencies using `composer install`, then run `php artisan migrate`.
   The PDF generator uses the Composer package `tecnickcom/tcpdf`; the PDFs
   are encrypted using AES-256 and require a user password.
2. Open **Super Admin → Email Configuration** and enter your SMTP server host,
   port, connection security (STARTTLS, SSL/TLS, or none), account username,
   account password, and the sender's email/name. Save the form, then use
   **Send test email** to confirm the connection. SMTP credentials are stored
   encrypted in the database using `APP_KEY`; the password is never shown
   again. Leaving the password blank while editing retains the saved value.
   Do not share SMTP passwords through a message or in application logs.
   **Enabled** makes this connection the mail transport for email campaigns.
   When disabled or not configured, the app uses the Laravel mail transport
   configured through `.env` as a fallback. Neither `log` nor `array` mailers
   can deliver production messages.
   Set `APP_URL` in `.env` to the externally reachable HTTPS URL of this CRM
   so that recipient unsubscribe links work outside your local network.
3. Use `QUEUE_CONNECTION=redis` (or `database`). For Redis set
   `REDIS_QUEUE_RETRY_AFTER=1200` so the queue visibility window exceeds the
   maximum 900-second job duration. For database queues set
   `DB_QUEUE_RETRY_AFTER=1200`. Do **not** use `QUEUE_CONNECTION=sync` for
   bulk email; the send action rejects that configuration.
4. Run **one dedicated worker** so batches are sent in order and the configured
   delay can pace deliveries:

   ```bash
   php artisan queue:work --queue=email-campaigns --tries=1 --timeout=900
   ```

   Keep the worker running with a process supervisor. Run other application
   queues, including telephony, through separate workers. The default development
   command does not start this dedicated email queue.
5. To import scans, install **Tesseract OCR** (English language package).
   To import PDFs, install **Poppler** utilities `pdftotext` and `pdftoppm`.
   Add these programs to `PATH` on the web server, or set
   `OCR_TESSERACT_BINARY`, `OCR_PDFTOTEXT_BINARY`, and
   `OCR_PDFTOPPM_BINARY` to their executable paths in `.env`.
   Tesseract and Poppler are OS programs, **not** Composer packages.

   Restart PHP and queue workers after changing `.env` settings. SMTP changes
   made in the Super Admin screen are read from the database by new
   batches, without editing `.env`.

## SMTP configuration

Typical SMTP connection settings:

| Setting | Example | Notes |
| --- | --- | --- |
| SMTP host | `smtp.example.com` | The server supplied by your mail provider |
| Port | `587` | Usually used with STARTTLS |
| Security | `STARTTLS` | Requires encrypted connection; `SSL/TLS` commonly uses port `465` |
| Username | `mailer@example.com` | Leave blank only if your SMTP server does not require authentication |
| Password | *Provider app password or SMTP token* | Saved encrypted and never displayed |
| From email | `mailer@example.com` | Must be an address the provider permits you to send from |
| Sender name | `Your Company` | Name recipients see in their inbox |

The **Send test email** action sends a real message only after you click it and
specify the test recipient address. SMTP connection or login failures show a
short error on the page; review server logs for diagnostics. Use the secure
provider credentials supplied for SMTP rather than your normal personal login
password where an app password is supported.

## Operating guide

1. Create a message template. Enter the subject and message body as plain text.
   `{{name}}` and `{{email}}` substitute the recipient's details.
2. Enable the PDF option to include a personalized PDF. Enter PDF text and a
   password of at least eight characters. Send the password to recipients over
   a separate, trusted channel; do not include it in the same message.
3. Alternatively, upload a PDF, PNG, JPG or WEBP to **Import with OCR**.
   Extracted text becomes a new template. **Edit the imported template and
   proofread the recognition** before using it in a campaign.
4. Upload a UTF-8 CSV with an `email` heading and optional `name` heading:

   ```csv
   email,name
   alice@example.com,Alice Reyes
   bob@example.com,Bob Santos
   ```

   Duplicate addresses are deduplicated case-insensitively; opted-out
   addresses are skipped. An import can contain up to 20,000 distinct
   recipients and must be authorized for email contact.
5. Choose a batch size between 1 and 100 and a delay (in seconds) between
   batches, then create the campaign as a **draft**. Confirm the campaign before
   selecting **Start sending**.
6. Refresh the screen to view queued, sending, sent, failed and completed
   counts. **Cancel** prevents further queued recipients from being sent,
   but any message already being delivered might finish.

Each email has an unsubscribe link. Opt-outs are recorded globally and excluded
from future campaign imports and pending deliveries. Recipient addresses are
sent individually and are never exposed through CC or BCC.

## Limits and operations

- Campaigns schedule batches on one named queue. A single dedicated worker
  preserves pacing; the actual email delivery speed also depends on the SMTP
  provider's limits. Set batch size/delay to meet your provider's quotas.
- The PDF password is encrypted at rest using Laravel `APP_KEY`. Back up this
  key securely. A lost key makes existing stored PDF passwords unusable.
- Scanned PDF OCR inspects up to the first five pages; proofread the results.
  Supported OCR import size is 10 MB.
- Delivery status `sent` indicates acceptance by the configured mail transport,
  **not** confirmed recipient inbox delivery. Delivery/bounce webhooks are
  provider-specific and are not tracked in this feature.
- The queue job records per-recipient transport failures. If a worker crashes
  after an SMTP provider accepts a message but before updating the database,
  its status may require manual review. Check server mail logs and provider
  activity before retrying an interrupted batch.

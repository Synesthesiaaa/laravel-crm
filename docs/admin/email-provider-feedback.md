# Email provider feedback, suppression and tracking

## What the metrics mean

SMTP acceptance records that the outgoing transport accepted a submission. A Postmark delivery event records acceptance by the receiving server; neither establishes inbox placement or a human read. Ordinary SMTP campaigns have unavailable provider-delivery metrics. Selecting Postmark without configuring its callback also leaves delivery metrics unavailable.

Campaign metrics count unique recipients, including separate accepted, delivered, hard/soft bounced, complained, blocked, uncertain, failed, skipped, cancelled and unsubscribed outcomes. Observed opens and clicks are separate from `open_count`, which continues to represent unfinished work. Delivery and bounce rates use accepted recipients; engagement rates use delivered recipients where provider coverage exists, otherwise explicitly labelled accepted recipients. Zero denominators return unavailable rates.

Open/click observations can include mail-client proxies, link scanners and other automation. They are not evidence of consent or human readership. Tracking defaults off, is disabled for confidential/PDF/payroll delivery, respects DNT and Global Privacy Control by default and does not persist raw IP addresses or user agents. Public tracking routes must exclude the application's request-audit middleware, including for visitors who already have an authenticated CRM session.

## Postmark setup

The initial provider adapter supports authenticated Postmark Delivery, Bounce, SpamComplaint, Open and Click receipts. Only use this adapter for SMTP campaigns sent through the configured Postmark account and message stream.

1. A Super Admin configures provider `postmark`, server ID, message stream and separate callback Basic Auth username/password. The password is stored encrypted and must not be included in screenshots, audit payloads or ordinary logs.
2. Configure the public HTTPS `POST /api/email/postmark` endpoint in the matching Postmark server/message-stream webhook settings. Configure HTTP Basic Auth using the same callback credentials; do not place secrets in a query string. Optional inbound IP restrictions require the proxy's trusted client-IP configuration to be correct.
3. Enable delivery, bounce and complaint feedback. Leave provider open/click tracking disabled unless the approved campaign tracking policy permits those events. Configure Postmark not to include full bounce/message content.
4. Verify that reverse proxy HTTPS detection is correct. The application rejects HTTP callbacks even if the credentials are otherwise valid.
5. Use an explicitly approved tiny campaign and mailbox on the target deployment to verify real feedback. Unit/feature tests use synthetic JSON and do not prove provider connectivity or mailbox delivery.

Every submitted Postmark message includes opaque attempt correlation in `X-PM-Metadata-attempt`. A receipt must match the configured server/stream, frozen campaign provider identity, recorded transport-entered attempt, expected recipient and provider message ID. Unsupported, mismatched or conflicting receipts cannot change campaign outcomes. A valid receipt is durably stored before acknowledgment; the application retains safe event fields instead of full provider JSON, message contents, IPs or recipient address copies.

Stable provider trace IDs or deterministic event-specific fallback keys deduplicate receipts. Queued processing is repeatable, handles feedback arriving before the SMTP return and preserves verified provider acceptance. Provider evidence does not trigger a resend. Event processing uses the same campaign/recipient lock order as sending to reduce concurrency hazards.

## Suppression and unsubscribe

Marketing opt-outs apply only to marketing campaigns. Hard-bounce and complaint suppressions apply to all campaign purposes. Soft bounces are recorded for review and do not create an automatic permanent block. Operators must resolve an applicable security/provider block before transactional or payroll delivery; changing the campaign purpose cannot bypass it.

Super Admin can search suppressions and add/remove manual blocks. Provider complaints, hard bounces and unsubscribe records are protected from that removal action. Removing one manual suppression does not remove another active suppression or a legacy global marketing opt-out. Contact imports must never reactivate an opted-out or provider-blocked address.

New marketing messages use opaque unsubscribe tokens and one-click POST headers. GET displays a confirmation page and never unsubscribes automatically, protecting against link-scanner visits. A valid explicit/one-click POST creates an idempotent global marketing opt-out and records campaign attribution. Historical signed unsubscribe links continue through their existing handler.

Tokens are stored as hashes, do not contain recipient addresses and are not recorded by request-audit middleware. Click destinations are fixed by approved message content and stored before sending; a caller-supplied destination/query parameter is ignored. Unsubscribe links are excluded from click rewriting. Keep public token URLs out of third-party analytics and retain the no-referrer headers.

## Recovery and retention

The dedicated email consumer processes `ProcessEmailProviderEvent` jobs, with safe idempotent retries and backoff. The `email-feedback:maintain` command redispatches unprocessed receipts older than two minutes and expires tracking/processed-event records according to configuration. It does not submit campaign emails.

Schedule the command every five minutes with overlap protection. A live queue consumer and scheduler remain deployment responsibilities; application tests cannot prove they are running.

- `email_campaigns.tracking_retention_days`: default 90 days for open/click tokens.
- `email_campaigns.event_retention_days`: default 90 days, minimum seven, for processed minimal provider receipts.
- `email_campaigns.tracking_respect_privacy`: default true.
- Unprocessed provider receipts, suppression records and unsubscribe tokens are not deleted by this maintenance command.

Retention expires detailed minimal receipts/tokens; aggregate recipient outcome timestamps remain until the campaign/recipient retention policy removes them. Provider-held copies and backups have separate retention policies. Suppression evidence must not be deleted by a general import or tracking cleanup.

## Validation boundaries

The focused suite is `tests/Feature/Admin/EmailCampaignTrackingTest.php`. It uses isolated SQLite, synthetic attempts and fake queues; it exercises authentication, correlation, duplicates, early provider acceptance, safe suppressions, tracking privacy, fixed redirects, scanner-safe unsubscribe, retention/recovery and protected administration.

Before claiming production readiness, verify provider credentials and actual event receipt, supervised workers, scheduled recovery, reverse-proxy HTTPS behavior, MySQL concurrent event-versus-send handling and an approved mailbox's receiving-server delivery evidence. No such live verification is implied by this document.

Provider contracts: [SMTP metadata](https://postmarkapp.com/developer/user-guide/send-email-with-smtp), [delivery events](https://postmarkapp.com/developer/webhooks/delivery-webhook), [bounce events](https://postmarkapp.com/developer/webhooks/bounce-webhook), [complaints](https://postmarkapp.com/developer/webhooks/spam-complaint-webhook).

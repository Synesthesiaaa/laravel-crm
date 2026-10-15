# SMTP integration

The CRM supports enabled database SMTP managed by **Super Admin → Email Configuration**, with environment-mailer fallback when database settings are disabled or absent. Do not put credentials in documentation, CSV files or source control.

Use the canonical [SMTP configuration section](../EMAIL_CAMPAIGNS.md#configure-and-test-smtp) for exact fields, environment variable names, security semantics, tests and approved-campaign snapshots. Use [deployment and queue reliability](../EMAIL_CAMPAIGNS.md#deployment-and-queue-reliability) for the separate `email-database` / `email-campaigns` consumer and [incident handling](../EMAIL_CAMPAIGNS.md#progress-and-safe-incident-handling) for interrupted outcomes. A successful transport return records acceptance, not confirmed delivery; provider delivery callbacks are not implemented.

The application validates configuration syntax, not provider account/domain approval, DNS authentication or delivery reputation. Obtain provider-approved host, port, security, sender and quotas. A UI readiness indicator does not test connectivity. Request a synthetic SMTP test only with authorization for that actual message.

Sources: [SMTP service](../../app/Services/EmailSmtpService.php), [configuration controller](../../app/Http/Controllers/Admin/EmailConfigurationController.php), [mail configuration](../../config/mail.php), [preflight snapshots](../../app/Services/EmailCampaignPreflightService.php).

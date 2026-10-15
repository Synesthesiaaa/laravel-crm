# Email Configuration

**Access: Super Admin only.** Open `/admin/email-configuration` to manage enabled database SMTP settings, sender identity and an explicitly requested real test message.

Follow [Configure and test SMTP](../EMAIL_CAMPAIGNS.md#configure-and-test-smtp) in the canonical guide for field constraints, secure transport choices, credential preservation, environment fallback and test behavior. The configuration test sends to the entered recipient; the campaign review self-test uses the signed-in operator's account email and synthetic content without PDFs. Neither proves inbox delivery.

Disabling database SMTP restores environment fallback. Already frozen campaigns retain their approved transport and sender. Use the canonical [incident handling guidance](../EMAIL_CAMPAIGNS.md#progress-and-safe-incident-handling) for stopping/reconciling campaigns.

[Configuration controller](../../app/Http/Controllers/Admin/EmailConfigurationController.php) · [SMTP service](../../app/Services/EmailSmtpService.php) · [SMTP integration entry](../integrations/smtp.md)

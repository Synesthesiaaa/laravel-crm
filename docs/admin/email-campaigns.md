# Email Campaigns

**Access: Super Admin only.** Use `/admin/email-campaigns` for templates, recipient imports, drafts and progress. Admin, Team Leader and Agent cannot operate this area.

Read the canonical [Email Campaigns and Private PDF Delivery guide](../EMAIL_CAMPAIGNS.md) before preparing a campaign. It covers the SMTP prerequisite, templates/OCR, generic and strict CSV contracts, shared versus personal PDFs, payroll restrictions, review/dry-run, test-to-self, explicit sending, cancellation, blocked-only retry, provider reconciliation, reports, opt-outs and retention.

Use the [synthetic generic CSV](../examples/email-recipients.csv) or [personalized manifest](../examples/email-personalized-recipients.csv) as a formatting reference only. Never place employee PDFs in a shared attachment slot. Declared filename mapping does not prove document content ownership; verify it before authorizing dispatch.

[Processing diagram and text flow](../diagrams/email-campaign-processing.md) · [Source routes](../../routes/admin.php) · [Campaign controller](../../app/Http/Controllers/Admin/EmailCampaignsController.php)

# Email template composer

The new email-template workspace supports reusable plain-text and visual/HTML messages. It complements the existing confidential PDF/OCR template workflow; generated protected PDF templates continue to use that Super Admin workflow.

## Access and composition

- Admin can create and manage their own templates that have no generated PDF and are not associated with confidential/transactional campaigns. Super Admin can manage eligible templates across owners.
- Templates referenced by an approved, scheduled, active or completed campaign are locked. Create another template for changed content. Frozen campaign snapshots remain the delivery authority.
- Choose **Visual / HTML** for the self-hosted GrapesJS newsletter composer, or **Plain text** for escaped text with preserved line breaks.
- Visual blocks can be dragged into place. The Add block, Selected block, Move up, Move down and Remove block controls provide keyboard alternatives for top-level blocks.
- HTML source remains available if the editor fails to load. Visual content is exported with inline styles before saving. Builder project JSON is stored separately; reopening reconstructs components from the server-sanitized HTML rather than executing untrusted project JSON.
- Desktop and mobile previews use synthetic recipient values in a scriptless sandbox. The preview is an approximation; clients can render email differently.

## Supported content

Supported tags include text/headings, lists, basic formatting, links, images and email tables. An allowlist preserves common inline typography, spacing, border and layout styles. Scripts, event handlers, forms, inputs, embedded objects, remote stylesheets and unsupported markup are removed. CSS URL imports/background URLs and unsafe merge contexts are rejected.

HTML must be valid UTF-8 and no larger than 200 KB. Plain-text messages are limited to 50,000 characters. Builder JSON is limited to 500 KB. A message must contain readable content or a valid supported image.

Merge tags are `{{name}}`, `{{email}}`, `{{employee_id}}` and `{{period}}`. They are permitted in the subject and visible message text. Tags in HTML attributes, links or styles are rejected. Missing name becomes Recipient; other required merge values must be supplied. Values inserted into HTML are escaped. Subject merge values cannot introduce line breaks or NUL characters.

Original plain-text templates retain their escaped rendering even though the historical database column is named `html_body`.

## Images and previews

Set `email_campaigns.image_hosts` to a list of approved public image hostnames. Images must use HTTPS without embedded credentials, explicit ports or IP-literal hosts, and their path must end in PNG, JPEG, GIF or WebP. SVG, data URLs, arbitrary remote hosts and empty allowlists fail closed. No image-upload service or server-side remote URL fetching is performed by this module.

Both visual canvas and preview apply a restrictive resource policy. The preview has an empty sandbox, no referrer, no scripts and only configured image origins. Saving and previewing both use the server sanitizer; client-side editor behavior is not a security boundary.

## Rendered synthetic tests

Save the template, then explicitly choose **Send synthetic test to myself**. The saved body/subject are personalized with sample name, `sample@example.com`, a sample employee ID and period. The destination is exclusively the signed-in operator's valid account email; a caller-supplied recipient is ignored. No campaign recipients, private documents, generated PDFs, CC or BCC are included.

The test uses configured current SMTP and is independent of campaign approval, queue state and bulk sending opt-ins. A successful action records transport acceptance, not confirmed inbox delivery. SMTP/template failures appear in a separate test error bag and do not invalidate a campaign's mapping approval. The route must retain its test rate limit.

## Integration contracts

- `TemplateComposer::sanitize(string $html): string` validates the content contract and returns safe email HTML.
- `TemplateComposer::render(EmailTemplate $template, array $values): string` safely personalizes HTML or escaped legacy plaintext.
- `TemplateComposer::subject(EmailTemplate $template, array $values): string` personalizes a header-safe subject.
- `TemplateComposer::snapshot(EmailTemplate $template): array` supplies format-aware sanitized composition properties for an encrypted approved snapshot, excluding builder JSON.
- `EmailTemplateController::preview` returns sanitized synthetic `html` and sanitized token-preserving `source` as private/no-store JSON.
- `resources/js/email-template-editor.js` dynamically imports GrapesJS, its newsletter preset and editor CSS only when an editor surface is present. Import the small entry module from the main app bundle; soft-navigation teardown destroys its editor and aborts pending requests.

Focused checks:

```powershell
php artisan test --compact --no-coverage tests/Feature/Admin/EmailTemplateComposerTest.php tests/Feature/Admin/EmailTemplateManagementTest.php
node --check resources/js/email-template-editor.js
```

The feature suite requires the main application's registered template routes and additive template fields. Browser/rendered-mail acceptance is recorded with the whole campaign change; this guide does not claim production SMTP or mailbox validation.

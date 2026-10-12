## Why

The current campaign worker can only generate a PDF from template text and has no verified document-to-recipient mapping. Confidential delivery needs private ingestion, an immutable manifest and truthful transport outcomes; the dedicated queue also needs an actual consumer.

## What Changes

- Add marketing, transactional and payroll purposes and explicit shared/personal/mixed PDF attachment modes.
- Import strict recipient manifests with ZIP or multiple PDFs into encrypted private storage, reject unsafe archives and verify exact mappings and hashes.
- Add authenticated review, preview, preflight, dry-run, confirmation, redacted status exports and retention.
- Freeze sender/template/recipient/document snapshots; claim recipients atomically and preserve uncertain outcomes without automatic resend.
- Preserve legacy CSV, OCR templates, generated PDFs, SMTP settings and marketing opt-outs.
- Provide dedicated worker heartbeat, recovery and deployment instructions without dispatching real mail during development.

## Capabilities

### New Capabilities
- `secure-email-delivery`: Confidential document ingestion, review, delivery and lifecycle.

### Modified Capabilities
None.

## Impact

Additive email tables/columns, focused email services, admin routes and Blade views, existing delivery job, email-specific queue configuration, tests and runbook. No telephony or HR data changes; no new PDF dependency. Uploaded PDFs requiring password protection remain blocked unless a verified processing solution is configured.

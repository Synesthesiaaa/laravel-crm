## Why

The existing email module safely imports CSV lists, freezes approved document mappings and submits messages asynchronously, but lacks the audience management, scheduling, visual composition and provider-backed reporting needed to operate marketing campaigns. Extend that implementation while preserving its confidential-document protections and its distinction between transport acceptance, confirmed delivery and uncertain submissions.

## What Changes

- Add a campaign dashboard, searchable histories, live status polling, performance charts, accessible progress indicators and exportable reports with explicit metric definitions and unavailable-data states.
- Add background manual/CSV/XLSX/CRM audience preparation, reusable contacts and segments, deduplication, consent provenance, suppression management and unsubscribe attribution.
- Add a self-hosted drag-and-drop email editor, sanitized HTML editing, reusable designs, safe personalization, sandboxed live previews and operator-address campaign tests.
- Add timezone-aware scheduling, pause/resume/cancel, application lifecycle state transitions, durable dispatch recovery, native Laravel batches, shared provider rate limits and safe bounded retry/backoff.
- Add an authenticated Postmark provider-event adapter, idempotent event processing, bounce/complaint suppression and optional privacy-aware opens/clicks. Other SMTP providers remain usable with honest acceptance-only reporting until an adapter is implemented.
- Allow Admins to manage their own marketing campaigns and audiences. Keep SMTP settings and confidential/transactional/payroll workflows restricted to Super Admin; exclude Team Leader and Agent.
- Preserve historical campaigns, legacy frozen snapshots, private PDFs, OCR imports, generated PDFs, dry-runs and evidence-backed uncertainty reconciliation.
- Add focused automated/browser/concurrency validation and deployment documentation. Production readiness requires actual supervised-worker, scheduler, provider-event and approved mailbox acceptance evidence; synthetic tests alone do not satisfy it.

## Capabilities

### New Capabilities
- `email-campaign-management`: Campaign lifecycle, audience preparation, composition, queue orchestration, scheduling, reporting, provider events, permissions, consent and operational acceptance.

### Modified Capabilities
- `secure-email-delivery`: Preserve Super Admin-only confidential workflows while adding isolated Admin-owned marketing access, new lifecycle compatibility and versioned composition without weakening immutable mapping or uncertain-outcome protection.

## Impact

- Additive email-domain migrations, new models/requests/policies/services/jobs/controllers/notifications, named web and authenticated JSON endpoints, a provider webhook and signed tracking/unsubscribe routes.
- Existing email services and Blade screens, navigation, scheduler, dedicated queue configuration and Supervisor runbook.
- Added dependencies: self-hosted GrapesJS 0.23.9 and newsletter preset 1.0.2, HTMLPurifier 4.19.1 and OpenSpout 4.32.0; Composer/npm locks record the compatible installed versions.
- No telephony/provider provisioning changes, live data migration, production sending, global access expansion or destructive replacement of historical email data.
- Design was approved by the user. Application implementation is present; deployment acceptance remains pending. The canonical design is `docs/superpowers/specs/2026-11-11-email-campaign-management-design.md`.

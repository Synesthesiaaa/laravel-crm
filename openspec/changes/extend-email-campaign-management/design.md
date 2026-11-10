## Context

The existing Laravel 12 email subsystem provides dedicated asynchronous sending, CSV audiences, encrypted SMTP settings, private document mapping and immutable delivery snapshots. It lacks scheduling, rich composition, segmentation and provider-event analytics. Preserve that implementation while adding the requested campaign lifecycle.

The canonical approved design is [Email campaign management design](../../../docs/superpowers/specs/2026-11-11-email-campaign-management-design.md). The implementation extends existing services, retains the protected sender and adds asynchronous approval on a separate preparation worker. See [operator guide](../../../docs/EMAIL_CAMPAIGN_MANAGEMENT.md).

## Goals / Non-Goals

**Goals:** Implement the requested application lifecycle and accurate reporting, preserve legacy and confidential-document behavior, and validate roles, idempotency, concurrency, imports, scheduling, events and UI.

**Non-Goals:** Replace telephony, provision provider accounts, send production blasts, fabricate consent/delivery evidence, or claim operational acceptance based on synthetic transports.

## Decisions

- Incrementally extend the existing sender rather than replace historical campaigns or confidential delivery.
- Add Admin-owned attachment-free marketing policies while preserving Super Admin-only SMTP and confidential/transactional/payroll access.
- Add background private audience staging for manual/CSV/XLSX/explicitly mapped CRM sources, normalized contacts, consent provenance and allowlisted saved segments.
- Use bundled GrapesJS/newsletter composition, HTMLPurifier server-side sanitization and OpenSpout streaming XLSX reading; package locks record installed compatible versions.
- Introduce additive lifecycle/schedule/outbox/attempt/event/suppression/notification persistence and version-3 snapshots. Preserve version-1/2 verification and historical labels.
- Use native Laravel batches for orchestration, durable dispatch intents for recovery, atomic recipient claims for application idempotency, shared rate limits and exponential retries only for verified non-submission.
- Implement Postmark as the first authenticated event adapter. Generic SMTP stays supported with acceptance-only reporting. Do not equate receiving-server delivery with inbox placement.
- Default optional tracking off; use opaque tokens, frozen destinations, minimal retained events and explicit coverage/observation labels.
- Reuse the existing Blade/Alpine design system with bounded live polling, paginated searches, streamed exports and safe creator notifications.

## Risks / Trade-offs

- [SMTP acceptance can be uncertain] -> Preserve uncertain states and require evidence before retry.
- [Dispatch/event races] -> Unique outbox/event keys, attempt correlation, generation checks and MySQL concurrency tests.
- [HTML and imports add attack surfaces] -> Server allowlists, sandboxed previews, private bounded staging, spreadsheet/archive limits and authorization tests.
- [Operational infrastructure may be unavailable] -> Keep real SMTP/provider/worker/scheduler acceptance tasks open; never archive incomplete work.

## Migration Plan

Use additive reversible migrations without rewriting historical statuses or snapshots. Rollback/reapplication was rehearsed on isolated SQLite with legacy rows retained. Existing live/local flags keep real bulk sending disabled until approved deployment. Deploy compiled assets, supervise delivery and preparation workers and configure HTTPS/provider credentials. Archive only after automated/browser and required operational acceptance passes.

## Open Questions

The user approved the design and implementation. Live provider credentials, sender identity, approved test mailbox, host inspector/scanner policy and server access remain deployment acceptance prerequisites. MySQL race tests are present and wired into CI but local MySQL validation was unavailable; their passing execution remains required.

## Implementation refinements

- HTTP approval records a durable request and draft digest; a long-timeout preparation job verifies consent and freezes version-3 snapshots before creating dispatch intent. Concurrent draft/audience changes are blocked while approval is pending.
- Private uploads use chunk-encrypted staging and a leased background worker. Existing draft creation was extracted into a shared service so exact mapping and old integration endpoints retain their behavior.
- Resume preserves a future scheduled instant and derives completion if the last in-flight recipient finished while paused. Stale dispatch generations become obsolete, and fresh delayed work is not requeued every minute.
- Provider callbacks and sender completion use compatible campaign/recipient/attempt lock order; late verified evidence can correct a previously revalidated pending recipient without causing a resend.
- Application tests, 10,000-recipient synthetic performance and isolated browser lifecycle evidence are recorded in `validation.md`. No production deployment or delivery is claimed.

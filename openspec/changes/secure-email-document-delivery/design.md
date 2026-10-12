## Context

Existing Blade/Alpine email module uses offset batches on a dedicated queue. Local baseline: 18 tests pass; no email worker process is running. Uncommitted queue diagnostics and UI improvements must be preserved.

## Goals / Non-Goals

Deliver exactly the explicitly mapped PDF to one recipient; provide shared PDFs, private review, integrity checks, frozen content and reliable states. Preserve generic campaigns. Exclude payroll calculations, real development sends, provider webhooks and invented legal policy.

## Decisions

- Use a unified document table with nullable recipient ID, composite campaign/recipient foreign key, filename/hash/size metadata and encrypted blobs on a dedicated non-serving local disk. Bounded per-file reads enable authenticated encryption with Laravel APP_KEY; process files individually, not entire archives in memory.
- Strict CSV parsing for sensitive/personal delivery preserves email local parts, rejects case collisions, validates allowlisted variables and matches exact root-level PDF names. Preserve historical marketing deduplication for generic CSV.
- ZIP entries are streamed into a random private workspace after metadata safety checks. Reject directories, encrypted entries, symlinks, nested archives and collisions rather than guessing paths. Approved Poppler pdfinfo validates PDF structure; the existing TCPDF package has no parser in this checkout. Encrypted uploads and unavailable verification are rejected. Optional configured scanner executes without a shell; required scanning without a scanner blocks. Newly required scanning also blocks previously unscanned documents.
- Preflight freezes encrypted template/sender settings and recipient/document snapshots at queue time; a snapshot digest detects edits. Super Admin is the only authorized role; do not invent HR grants. Payroll requires explicit organization policy enablement; password-protection-required payroll stays blocked because original PDF encryption is not implemented.
- Queue dedicated connection aliases keep visibility timeout above bounded per-recipient job timeout. Dispatch one recipient per ID-only job, atomically claim under campaign lock, verify all intended bytes before network IO. New generic batches distribute recipient jobs; existing legacy queued batches remain readable. Each verified original is streamed through a restrictive private MIME workspace, with cleanup and abandoned-copy recovery. Mark any possibly submitted transport failure uncertain; only known pre-transport blocked work can be retried after revalidation. No blind uncertain retry. Super Admin may record explicit provider evidence with actual acceptance time or verified no-submission; this audit operation never sends mail.
- Audit metadata contains IDs/counts/actions only. Scheduler purges expired blobs including drafts and cancelled campaigns, marks stale sending uncertain, and stores a consumer heartbeat via queue loop events. Recovery requeues pending work idempotently; accepted/uncertain rows remain untouched.
- Operate UI: purpose/mode fields, draft import, dedicated mapping review with authenticated previews, dry-run gate, explicit confirmation and status counts. Reuse form/table/panel tokens and provide bounded refresh without live-region chatter.

## Risks / Trade-offs

- SMTP cannot guarantee exactly-once delivery → uncertain reconciliation requires operator evidence.
- APP_KEY loss/rotation impacts retained encrypted snapshots/files → document key backups and rotation retention window.
- Uploaded PDF password protection unavailable → block when mandated, never re-render originals.
- Dedicated worker heartbeat cannot prove future uptime → block stale heartbeat at queue time and monitor pending age.
- Existing campaigns keep historical sent status → label it provider acceptance; never claim inbox delivery.

## Migration Plan

Deploy additive migration before new code; preserve existing rows and defaults. Restart email workers on dedicated connection, run scheduler, approve policy then synthetic rehearsal. Rollback only before new confidential campaigns are created; purge/export approved metadata first when reverting data structures. No production migration runs as part of development validation.

## Open Questions

Organization must approve payroll purpose, retention period, scanner and original PDF password policy before live payroll enablement.

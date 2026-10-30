## Context

User Access already has a single-user form and paginated staff table behind `role:Super Admin`. The shared CSV importer streams validation and creates users through UserService. Its temporary SQLite index and whole-file call must be adapted for durable web jobs without altering CLI behavior.

## Goals / Non-Goals

**Goals:** Import directly from User Access; preview before creation; support large files without HTTP timeouts; persist progress across reloads; protect every endpoint and artifact; preserve existing user management and design tokens.

**Non-goals:** External provisioning, invitations, account updates/restores, a new visual system, or running real staff imports during validation.

## Decisions

- Extend the existing page with an inline panel. Use shared admin panel/buttons/fields, restrained semantic status, responsive counters, an accessible native file input, explicit states, and a short recent-import list. Avoid a modal and preserve the existing single-user form/table. Scope is an Operate-mode extension.
- Upload UTF-8 CSV up to 20 MiB; validate file extension and header before storing. Offer a template download. Agent remains default; a visible opt-in allows roles above Agent. The immutable upload is reviewed as a whole before an explicit start request; previews with rejected rows can import only valid candidates, clearly labelled.
- Three additive tables: import metadata/checkpoint, transient identity hashes, and safe row errors. Source file is private on a dedicated non-serving local disk under an opaque UUID; no endpoint downloads source credentials. Only the submitting Super Admin can inspect/start/cancel/download that import. The worker rechecks its owner's current role.
- Preview and creation run on the dedicated database-backed `user-imports` queue, independent of a sync/global queue configuration. Jobs serialize only import ID and phase. One job processes at most 50 CSV records per reservation, saves offset/counters/errors/identity hashes in the same database transaction as account creation, and releases itself for the next batch. A row lock serializes duplicate deliveries and cancellation. A retry resumes the committed offset; terminal/stale-phase jobs are no-ops.
- Reuse importer parsing, validation, hashing, encryption, and creation. Add an optional batch context and identity-store interface; CLI continues using its temporary SQLite store. Web uses the transactional CRM database store, so preview checkpoints do not depend on a second filesystem database transaction.
- Report downloads stream static diagnostics from the safe error table. Status payloads explicitly enumerate public fields; never serialize source rows, paths, passwords, or SQL exceptions. Poll with backoff and stop on component teardown; show queued/stale/error/retry states.
- Delete uploaded credentials immediately on completion/cancel/failure and after 24 hours for abandoned previews. A scheduled prune also removes old reports/metadata after seven days and orphan files. Source cleanup is idempotent; terminal jobs retry cleanup safely. Do not run unrelated scheduled production tasks during verification.

## Risks / Trade-offs

- [Worker absent] → UI stays truthfully queued; document and configure a matching dedicated worker. No synchronous fallback that could time out.
- [Raw CSV contains passwords] → Private non-serving storage, generated paths, no source downloads/logs, prompt deletion, short expiry, and deployment filesystem access controls.
- [Long hash time] → Small batches, 120-second job timeout, 360-second reservation, no lowered production hash strength.
- [Database/runtime failure] → Roll back the current batch, retain prior committed accounts, fail with static diagnostics, remove source; upload the corrected/original CSV again to skip completed usernames.
- [Transient database contention] → Deadlocks and lock conflicts roll back the full slice and release the same job to retry its unchanged checkpoint. Verified after concurrent browser/session writes reproduced this in the SQLite fixture.
- [Preview differs from import] → Final uniqueness remains authoritative; no promise of snapshot isolation across phases. Source digest is checked at phase start.
- [Local shared storage] → Web and workers must share the same application release, key, database, and private disk.

## Validation

Test authorization for all routes, ownership, upload validation/privacy, preview with no account writes, explicit confirmation/replay, checkpoint duplicate tracking, cancellation, role revocation, failure rollback/sanitization, expiry, template/report streaming, and unchanged CLI behavior. Verify the rendered workflow with isolated synthetic SQLite fixtures, real database-queue work, representative widths/themes, keyboard focus, console/network evidence, build and lint. Sync and archive after validation.

## Context

The affected paths cross form administration, dynamic table storage, attendance logs, CSV responses, operation results, Alpine stores, shared Blade controls, and realtime initialization. Current submissions may create or alter dynamic tables before database transactions, while admin metadata is independently stored. Fixes must preserve historical form rows, campaign behavior, the current storage nullability safeguards, and the existing UI design system.

The audit's C10 evidence concerns a pending-upgrade recovery risk only. It does not establish that the historical cleanup migration ran or deleted unauthorized data, so this change documents a preflight and leaves that migration unchanged.

## Goals / Non-Goals

**Goals:**

- Keep metadata and historical records connected when administrators edit forms.
- Limit authorized Data Master updates to configured business fields and apply the same typed rules as normal submissions.
- Serialize attendance state transitions and locate an open segment across midnight.
- Keep ordinary form and import submissions free of schema creation or alteration while retaining explicit provisioning and current nullability protections.
- Protect spreadsheet consumers and users from formula injection and internal exception disclosure.
- Keep optional browser features from blocking Alpine startup; make repeated controls uniquely labelled; preserve Activity Log styling and configured realtime transport through soft navigation.
- Minimize local customer data retention through explicit field opt-in, expiry, and logout cleanup.

**Non-Goals:**

- Do not rewrite or run the C10 migration, modify production records, or run destructive database changes.
- Do not change call, SMS, email, campaign, role, or API contracts beyond safe error text and verified defects.
- Do not add server-side draft storage, a frontend dependency, or a new design language.
- Do not claim live Asterisk, VICIdial, Reverb, Pusher, production database, or deployment verification from local tests.

## Decisions

1. **Protect form identifiers after use.** A form is used when its current or soft-deleted field metadata exists, or its storage table contains rows. Reject campaign, form-code, or table-name changes after that point; allow descriptive edits. Before use, preserve the existing ability to correct identifiers.
2. **Use registered field definitions as the Data Master write allowlist.** Share field-type rules between regular submissions and Data Master edits. Exclude system/provenance columns even when physically present. Preserve campaign/table checks and record lookup.
3. **Provision schema only from configuration operations or an operator command.** Move additive create/missing-column/numeric-width/nullability logic into a dedicated service. Call it after form/field changes and expose `forms:provision-storage` for controlled deployment recovery. A submission checks readiness for a metadata fingerprint and returns a safe error when stale; it never issues DDL. Preserve physical type/default/comment/collation/index/system/generated/auto-increment safeguards.
4. **Serialize attendance on the user row.** Acquire a transaction and lock the stable `users` row before reading or writing custom status events. Determine each status type's latest event across all dates and return the newest unmatched start.
5. **Encode CSV cells by type.** A shared encoder preserves strict numeric values as numeric cells. Other values, including headers and legacy values in numeric-configured fields, receive a text-safe prefix when they begin with spreadsheet formula markers after leading whitespace or control characters.
6. **Return opaque failure references.** Generate a UUID reference for unexpected persistence failures, log the reference plus exception class and bounded operation context, and return a stable user message containing the reference. Do not log exception messages, request payloads, credentials, or customer values.
7. **Make local drafts opt-in and short-lived.** Add an additive `draft_safe` flag defaulting to false. Persist only opted-in logical fields, enforce an eight-hour maximum age, remove legacy and expired/corrupt payloads, exclude phone values from keys, and clear drafts during graceful logout. localStorage remains optional and guarded.
8. **Keep browser fixes inside incumbent UI patterns.** Guard localStorage reads/writes and validate recent-search shape; render `_editing` as ordinary hidden markup; allow explicit IDs on form components and provide row-specific IDs; import scoped Activity Log styles through Vite; select the Echo broadcaster and matching key explicitly.
9. **Document C10 as a deployment preflight.** Operators must inspect migration status, affected columns/indexes, and tested backup restoration before applying the existing migration in an environment where it is pending. The migration remains unchanged.

## Risks / Trade-offs

- [Risk] Schema provisioning can fail after metadata is saved. -> Keep storage readiness stale, return a safe admin error, and make the idempotent command the recovery path.
- [Risk] Existing forms need provisioning before their first post-deploy submission. -> Document the command in deployment steps and fail closed with an actionable message when storage is stale.
- [Risk] Draft-safe opt-in changes retention for existing forms. -> Default false and explain its privacy impact beside the admin control.
- [Risk] Row locks vary by engine. -> Verify contention on isolated CI MySQL; SQLite tests cover reconstruction only.
- [Risk] Formula-safe text prefixes change spreadsheet display. -> Transform untrusted text only and preserve strict numeric values.
- [Risk] Browsers may block localStorage. -> Catch storage failures and keep forms and call controls operational.

## Migration Plan

1. Add `draft_safe` as an additive boolean with a false default; make the migration reversible.
2. Deploy code and run `php artisan forms:provision-storage` during a controlled deployment. The command creates missing registered storage structures/columns and uses only the existing nullable/numeric alignment behavior.
3. Admin configuration changes provision the affected form after saving metadata. If provisioning fails, submissions report a safe failure until an administrator reruns the command.
4. Rollback is code-only; the additive flag may remain unused and already-added nullable columns are retained.
5. For C10, review `migrate:status`, inspect candidate data and indexes, and verify backup restoration before an operator applies the historical migration.

## Open Questions

- The deployment owner must confirm the runbook location for operating the provisioning command; this change documents it in current operations guidance.
- MySQL contention is CI-only here; SQLite tests do not establish row-lock behavior.

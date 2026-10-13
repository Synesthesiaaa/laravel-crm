## Context

Field Logic uses soft deletion and configuration updates. FormSubmissionService ensures storage before its insert transaction, but only adds missing columns and widens numeric types. One-time migrations repaired earlier drift; later edits recreate it. The current local winback form differs from the screenshot, so regression fixtures must recreate its required removed column.

## Goals / Non-Goals

**Goals:** Allow submissions after deletion or optional edits without discarding historical data. Preserve legacy field-name mapping and required validation.

**Non-Goals:** Redesign UI, delete stored columns, change requiredness for active required fields, or run unrelated migrations.

## Decisions

- Reconcile nullability in existing storage preparation, after legacy field mapping and numeric alignment, outside the insert transaction. This repairs existing drift and future edits across all submission entry points.
- Make omitted non-system columns nullable only when they have no default. Make active optional columns nullable even if they have a default, because empty optional values are explicitly inserted as NULL.
- Use existing database metadata and Laravel raw column definitions to preserve type, collation, default, and comment when changing nullability; skip generated and auto-increment columns.
- Keep historical columns and values. Do not fill deleted fields with invented empty strings or numeric zero.
- Preserve one-to-one storage mapping before comparing active column names.

## Risks / Trade-offs

- Schema changes can briefly lock a table; alter only columns requiring reconciliation and verify subsequent submissions issue no ALTER.
- Restoring an optional column to required could invalidate existing rows; application validation remains responsible for active required fields and this fix only relaxes constraints.
- Browser authentication may be unavailable; use a disposable isolated application/database for end-to-end verification if necessary.

## Migration Plan

Deploy the service change. First valid submission repairs affected storage before insertion; no new migration or historical data deletion. Rolling back code retains nullable columns, since existing NULL values prevent safely reinstating NOT NULL.

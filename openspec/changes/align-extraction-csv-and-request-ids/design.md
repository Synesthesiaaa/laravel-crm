## Context

The extraction service currently streams each registered form table using the database driver's natural column order. That order diverges from the editable `form_fields.field_order` configuration and differs between legacy and dynamic form tables. The service also writes a header from the first returned row, which provides no predictable layout for an empty export.

New form submissions currently overwrite client input with a ULID. The required replacement is a numeric identifier comprised of the submission-time prefix and a six-digit secure random suffix; stored records must not be rewritten.

## Goals / Non-Goals

**Goals:**

- Make each form-table CSV layout predictable and aligned with that form's Field Logic ordering.
- Include `id`, `date`, `request_id`, `agent`, `created_at`, and `updated_at` when they exist in the table.
- Retain legacy, unconfigured business columns without allowing them to disrupt configured-field order.
- Generate numeric request IDs for new form submissions and detect per-table collisions before insertion.
- Preserve streaming behavior and current percentage-value presentation.

**Non-Goals:**

- Do not alter historical request IDs or backfill records.
- Do not change routes, export permissions, CSV encoding, or the submitted form UI.
- Do not create a cross-table global sequence or add a database-wide request-ID registry.
- Do not change Field Logic ordering controls; their existing `field_order` remains the source of export ordering.

## Decisions

### Define a canonical export layout per form table

The extraction service will obtain the table's actual columns and build an ordered list for every selected table:

1. existing primary/system columns in this order: `id`, `date`, `request_id`, `agent`;
2. existing `FormField` columns for the registered campaign/form, sorted by `field_order` then model ID;
3. existing non-system columns not represented in Field Logic, preserving their database order for legacy compatibility;
4. existing timestamp columns: `created_at`, `updated_at`.

The generated column list will be passed to the query selection, CSV header, and row formatting so rows always have the same number and order of values. This maintains the current one-header-per-table behavior for multi-form exports.

Using the database's raw column order was rejected because it ignores the administrator-owned ordering. Removing unconfigured columns was rejected because legacy stored data must remain extractable.

### Resolve Field Logic through registered forms only

`Form` records will be used to determine the campaign/form pair(s) associated with an exported table. Form fields will be limited to those pairs and intersected with real table columns. This is safe for missing tables, dynamic schema changes, and stale metadata.

Using field names without campaign/form scoping was rejected because the same table or field name could be present in multiple configurations.

### Generate numeric IDs at submission time

The submission service will replace the ULID assignment with a private generator that returns `now()->format('YmdHis')` followed by a six-digit value produced by `random_int()`. It will verify that the candidate does not exist in the target form table and retry a bounded number of times before failing without insertion.

Generating IDs during extraction was rejected because export must faithfully represent stored records. A timestamp-only sequence was rejected because simultaneous submissions could collide.

## Risks / Trade-offs

- [A six-digit random suffix can collide under exceptional concurrency] → Check the current table before insertion and retry a bounded number of candidates; the timestamp prefix and cryptographically secure randomness keep collisions rare.
- [An unconfigured legacy column has no administrator-defined position] → Retain it after configured fields, using database order only as a stable fallback.
- [A table may lack one or more expected metadata columns] → Include only columns physically present and never issue a query for absent columns.
- [An empty export has no rows from which to infer a header] → Build the header from schema and Field Logic before streaming rows.

## Migration Plan

1. Deploy the application change and tests; no data migration is required.
2. New submissions immediately use the numeric request-ID format while existing records retain their stored values.
3. If rollback is needed, restore the previous ULID assignment; exported ordering changes are presentation-only and do not mutate data.

## Open Questions

None.

## Context

Field Logic currently defines the physical and rendered form-field schema, but it has no campaign-specific way to identify fields that represent sale value. `DashboardStatsService` therefore counts sales from disposition records and extracts amounts from a fixed list of keys in `lead_data_json`. Form submissions already persist `date`, `agent`, `created_at`, and dynamic field columns, which provides the source needed for configurable field-driven sales.

## Goals / Non-Goals

**Goals:**

- Persist a boolean sale-amount designation on form fields.
- Let administrators set the designation while adding or editing fields and see it in the field list.
- Count each submission row with at least one non-empty numeric marked value as one sale.
- Sum all non-empty numeric marked values on that row into its sale amount.
- Apply the same source consistently to the rolling KPI and month-to-date agent leaderboard.
- Preserve existing campaign behavior through a disposition fallback when a campaign has no marked fields.

**Non-Goals:**

- Adding a new sales ledger or public API.
- Backfilling historical disposition records into form submissions.
- Changing how call dispositions are saved or validated.
- Counting a row more than once when multiple sale fields are populated.

## Decisions

### Store the designation on `form_fields`

Add a nullable-safe boolean column named `is_sale_amount` with a false default, include it in `FormField::$fillable`, and cast it to boolean. This keeps the configuration beside the field name, type, form, and campaign that interpret the value. A separate configuration table would add joins and lifecycle complexity without supporting a need not present in the request.

### Restrict the designation to numeric fields

The UI will expose the checkbox only when the selected field type is `number`; the controller will persist the flag only when the effective field type is `number`. This prevents text, select, and percentage fields from silently becoming sale sources. Existing marked fields are cleared if an administrator changes their type away from `number`.

### Aggregate field-driven submissions in `DashboardStatsService`

Resolve marked fields by campaign and form, map each form to its registered physical table, and inspect only marked columns that exist on that table. A row qualifies when any marked value is non-null, non-empty, and numeric. The row contributes one sale and the numeric sum of all qualifying marked values. The rolling KPI uses `created_at` for the configured hour window; the month-to-date leaderboard uses the existing `date` range and groups by `agent`.

When no active marked fields exist for a campaign, retain the current disposition-based count and configured lead JSON amount extraction. When marked fields exist, field-driven totals are authoritative so a submission can count as a sale without a `SALE` disposition and the same sale is not counted twice from two unrelated sources.

### Invalidate dashboard caches after configuration changes

Field Logic writes will call the existing dashboard invalidation path for the affected campaign in addition to clearing campaign configuration cache. Form submission events already invalidate related dashboard data; the aggregation methods continue to use the current cache keys and TTLs.

### Keep tests at service and feature boundaries

Extend `FieldLogicAdminTest` for checkbox persistence and numeric-only behavior. Extend `DashboardStatsServiceTest` with physical form rows and marked `FormField` records covering count, sum, empty values, multiple marked fields, and fallback behavior. Render assertions verify the add/edit controls and list label; browser verification covers the admin page and dashboard output.

## Risks / Trade-offs

- [Existing campaigns may have no marked fields] → Retain the current disposition fallback until an administrator configures a marked field.
- [A configured field column may not yet exist on a dynamically managed table] → Intersect configured fields with existing schema columns before querying; ignore missing columns safely.
- [Cached dashboard results can outlive a field-logic update] → Invalidate all affected campaign dashboard keys after store, update, and delete operations.
- [A malformed value could distort totals] → Require a numeric field type for configuration and ignore null, empty, or non-numeric values during aggregation.

## Migration Plan

1. Deploy the additive migration with `is_sale_amount` defaulting to false.
2. Deploy model, admin, and dashboard code; existing campaigns continue through the fallback path.
3. Administrators mark numeric fields in Field Logic; future dashboard requests use field-driven sales for that campaign.
4. Rollback is the normal migration rollback after code rollback; since the column is additive and defaults false, existing records remain valid.

## Open Questions

None. The approved behavior is that any submission with a marked, non-empty numeric value counts as one sale.

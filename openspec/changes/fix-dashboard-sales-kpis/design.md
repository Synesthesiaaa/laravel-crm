## Context

The dashboard aggregates marked sale values from form-storage tables by `created_at`. `FormSubmissionService` writes through the query builder, which does not set timestamp columns automatically, so new rows have null timestamps and never reach the rolling sales aggregation. The fall-back Top Agent query separately retains the Calls window even though the card is presented as a 24-hour sales metric.

## Goals / Non-Goals

**Goals:**

- Store real capture timestamps on every new form submission.
- Aggregate field-driven sales without a dependency on the telephony disposition table.
- Use the configured sales window for both marked-sale and disposition-fallback Top Agent data.
- Remove the Calls (9h) card while preserving the Sales total-value and Top Agent cards.

**Non-Goals:**

- Do not infer timestamps for historical rows that were stored without them; their exact capture time cannot be recovered reliably.
- Do not remove the Calls KPI service data or its configuration, which may be used outside this dashboard card.
- Do not change sale-attribution rules or existing database schema.

## Decisions

- Add `created_at` and `updated_at` in `FormSubmissionService::prepareFormRow`. This is the source of truth for dynamically stored submissions and avoids a fragile dashboard-only workaround.
- Gate only telephony-based calculations on the disposition table's presence. Marked form sales are independently queryable and must remain available when telemetry data is unavailable.
- Change the fallback Top Agent query to use `salesSince`. This makes the computation agree with the visible 24-hour card label. Keeping the 9-hour Calls cutoff was rejected because it produces a mislabeled result.
- Remove only the Calls stat-card from the Blade view. Retaining the service return value avoids needless breaking changes.

## Risks / Trade-offs

- [Historical timestamp-null rows remain outside precise rolling metrics] → New submissions receive exact timestamps; no inaccurate time reconstruction is introduced.
- [A deployment uses a form table without timestamp columns] → The dashboard already skips such dynamic rows safely; current and newly created tables include timestamp columns.
- [Cached KPI data lingers briefly] → Existing 60-second cache keys remain in use and normal invalidation continues to clear them after form submission events.

## Migration Plan

1. Deploy the code changes.
2. Clear the application cache during deployment if configuration or view caches are enabled.
3. Submit a marked sale and verify its Sales total and 24-hour Top Agent value on the dashboard.
4. Roll back the changed application files if necessary; no schema or data rollback is required.

## Open Questions

None.

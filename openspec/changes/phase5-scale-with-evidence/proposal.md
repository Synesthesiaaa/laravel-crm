## Why

P1–P3 patterns are present in the current source, but the report correctly states that production bottleneck magnitude is unmeasured. Call-history filters and realtime reports materialize avoidable collections, historical sync fetches the entire remote window before writing chunks, email sends repeatedly hash/search the whole frozen recipient manifest and recount campaign statuses, and several dashboard paths repeat custom-sale rule evaluation and aggregation. This phase will reduce work and bound working memory using measured synthetic workloads while preserving call, report, campaign, and dashboard results.

## What Changes

- Replace PHP-side call-history filter deduplication with database-level distinct selection and deterministic ordering.
- Aggregate realtime “today” metrics in SQL and process rolling call windows in bounded primary-key chunks.
- Add keyset-paged VICIdial history streaming so each bounded remote page is normalized and upserted before the next page is fetched. Keep partial writes replay-safe: the sync checkpoint advances only after the complete window succeeds.
- Freeze new email campaigns as compact versioned campaign headers plus authenticated per-recipient snapshots, preserving the existing whole-manifest approval hash and compatibility for already-frozen campaigns.
- Maintain email campaign counters incrementally at serialized recipient state transitions and periodically reconcile them by campaign, rather than grouping all recipient statuses after every individual send. Add nullable counters so deployment does not backfill or rewrite existing production records.
- Extract the shared custom-sales rule evaluation and aggregation paths from `DashboardStatsService` into a focused service without changing public result shapes or rule semantics.
- Add repeatable synthetic workload benchmarks and focused tests. Do not add indexes unless measured query plans demonstrate a material benefit.

## Capabilities

### Modified Capabilities

- `telephony-operations-and-reporting`: Bound local aggregation and historical sync memory while retaining ordering, counts, and replay behavior.
- `secure-email-delivery`: Preserve immutable approval and attachment integrity with bounded per-recipient verification and accurate campaign totals.
- `campaign-dashboard-sales-rules`: Share custom rule evaluation and aggregation while preserving existing dashboard values.

## Impact

Affected areas include telephony history queries/providers/synchronization, realtime reporting, email campaign preflight/delivery/status transitions and a small additive migration, dashboard sales aggregation, the scheduler, and focused feature/integration/benchmark tests. Existing encrypted snapshots and version-1 queued campaigns remain supported. No existing record backfill, destructive migration, external email, real call, SMS, or production-data operation is part of validation.

## Context

The audit labels P1 as a verified scaling pattern with unmeasured production magnitude. Existing MySQL schema inspection shows useful `call_sessions` and `telephony_call_histories` indexes and a composite `(email_campaign_id, status)` index for recipient reconciliation. No additional index is justified without a query-plan and workload comparison. The VICIdial sync provider currently issues one ordered query over a bounded time window, calls `get()`, maps the full result, and only then chunks local upserts. The email provider stores encrypted campaign and recipient snapshots, but each recipient send decrypts, hashes, and searches the complete campaign manifest; `totals()` groups every recipient status after each individual send. Dashboard custom-sale rules are evaluated in several query paths inside `DashboardStatsService`.

## Goals

- Keep per-query application memory bounded for realtime reporting and VICIdial history synchronization.
- Keep call-history filter options and report metrics equivalent to current results.
- Keep email approval tied to the full dry-run manifest; keep each new recipient payload and campaign header independently authenticated; retain the version-1 verification path for already-frozen campaigns.
- Keep displayed email counters accurate after each recipient transition without a full recipient scan, and repair derived counters periodically.
- Move shared custom-sale evaluation/query/result assembly behind one cohesive service while retaining current date boundaries, decimal handling, output keys, and ordering.
- Record comparable before/after measurements using synthetic data and the configured 10,000-recipient default ceiling; never benchmark with live customer data.

## Non-goals

- No database indexes based only on intuition.
- No change to VICIdial APIs, call behavior, campaign authorization, email consent, recipient ordering, campaign status meanings, or dashboard business rules.
- No change to the historical report’s page-number pagination contract.
- No production data backfill, provider delivery, or live campaign execution.

## Decisions

### Call history and realtime reports

Use SQL `distinct` for agent/status options. Replace realtime Eloquent `get()` collections with a database aggregate for the local Today fallback and stable `chunkById` iteration for rolling metrics. Keep the same event-window predicate and non-negative duration calculations.

For VICIdial sync, add a streaming range method that uses Laravel cursor pagination over a stable non-null key tuple derived from call date, source table, and normalized source identity fields. Fetch at most the configured sync chunk size, normalize that page, pass it to the existing idempotent local upsert path, and release the page before requesting the next one. Keep the existing array-returning fetch method for other callers. A later-page or local-write failure leaves the prior checkpoint unchanged; retry replays pages through the existing unique source identity upsert.

### Email snapshot verification and counters

The preflight/dry-run hash remains over the full canonical manifest. On freeze, new version-2 campaign storage keeps only the immutable campaign header, while each recipient row keeps its snapshot and a keyed digest bound to campaign ID, recipient ID, and full manifest hash. The recipient snapshot includes the exact document metadata assigned to that recipient, so delivery does not scan every recipient/document entry. The campaign header has a separate keyed digest. Existing version-1 campaigns continue to use current full-manifest verification until they finish.

Serialize recipient status transitions by locking the campaign row before the recipient row. Track `open_count` and `attention_count` alongside existing sent/failed/skipped totals. Add nullable counters without migration backfill; initialize them from one grouped reconciliation when an older active campaign first transitions, then update them atomically. Preserve `needs_attention` for blocked/uncertain recipients and complete a campaign when no pending/sending recipients remain. A scheduled reconciliation repairs drift once per active campaign, not once per recipient.

### Dashboard sales extraction

Move the shared custom-trigger matching, numeric amount handling, select construction, chunking, and per-agent/per-form result assembly into `DashboardCustomSalesAggregationService`. Keep `DashboardStatsService` as the public facade and delegate its existing custom-sales methods. Reuse the evaluator in the summary and daily report loops. Leave unrelated dashboard responsibilities in place.

## Validation

- Add red-green regression tests for distinct filter options, exact realtime metrics, bounded sync page sizes/replay, versioned email snapshot authentication, counter transitions/reconciliation, and dashboard result parity.
- Run synthetic query/manifest/dashboard workloads at repeatable row counts; record wall time, peak PHP memory, query count, page size, and output equality before and after.
- Run MySQL-only counter contention and cursor-provider integration checks in CI; local SQLite tests must remain safe and isolated.
- Run focused PHP suites, Pint, the frontend build only if assets change, full relevant JS tests if applicable, strict OpenSpec validation, and a final diff review.

## Risks

- Keyset pagination requires a deterministic non-null order key. The provider must include fallback identity fields for rows with empty VICIdial `uniqueid`; tests must include such rows and tie cases.
- A later page failure may follow successful local upserts. This is safe only because the sync checkpoint remains unchanged and each page upsert is idempotent.
- Campaign counters span send, skip, interruption, cancel, retry, and uncertainty resolution. Every status writer must use the same transition service; reconciliation remains the recovery path for legacy and drifted counters.
- Per-recipient snapshots change shape. Version gating and legacy tests must prevent already-queued campaigns from being stranded.
- Synthetic SQLite timing is directional and cannot establish production MySQL throughput. CI MySQL verifies query behavior and concurrency; production impact remains unclaimed until measured with an approved representative workload.

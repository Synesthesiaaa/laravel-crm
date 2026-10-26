# Phase 5 synthetic benchmark results

Run on 2026-10-26 with `php vendor/bin/phpunit tests/Performance/Phase5SyntheticScaleBenchmarkTest.php`. The runner uses PHPUnit's in-memory SQLite database, synthetic records only, and 10,000 rows per workload. It asserts output parity and reports one sequential sample; the timing differences are directional, not production throughput claims.

| Workload | Baseline | Bounded/current | Result |
|---|---:|---:|---|
| Realtime rolling calls | 1,466 ms, 20.85 MB extra peak, 1 query | 1,171 ms, 1.61 MB, 21 queries | Same metrics; 92.3% lower measured extra peak memory |
| VICIdial history fetch | 438 ms, 44.40 MB, 1 query | 606 ms, 2.11 MB, 20 queries, max page 500 | Same identity set; 95.2% lower measured extra peak memory; SQLite paging was 38.4% slower |
| Email manifest and status work | 12.75 ms, 0.83 MB, 1 query | 8.62 ms, 0.22 MB, 7 queries | Same selected recipient and final counters; lower temporary memory, with more row-lock/update queries |
| Custom sales range | 52.96 ms, 0.54 MB, 15 queries | 51.77 ms, 0.54 MB, 15 queries | Identical aggregate output; no meaningful timing or memory change |

Memory is `memory_get_peak_usage(false)` above the test's pre-operation baseline. Query counts include Laravel's query log for the measured operation. The custom-sales baseline mirrors the former 1,000-row chunk/query shape; that change is an extraction and consistency improvement, not an asserted scaling gain.

## MySQL query-plan review

Read-only `EXPLAIN` queries against the configured CRM MySQL connection used synthetic campaign predicates and returned no customer rows:

- Recipient reconciliation selected the existing `(email_campaign_id, status)` index with `Using index`.
- Rolling call selection used the existing `call_sessions_campaign_code` index and applied the event-time predicates as a filter.
- Distinct local history agents used an existing campaign/agent/time composite index with `Using index`.

No new index is justified by these plans. No query plan was run against a live VICIdial database. The synchronization benchmark measures only the synthetic SQLite provider; its extra page round trips and slower local timing must be measured against an approved representative remote workload before making a production-latency claim.

## 1. Evidence and benchmark baseline

- [x] 1.1 Add a repeatable synthetic workload runner for call-history reporting, history-sync pages, email manifest verification/totals, and custom dashboard aggregation.
- [x] 1.2 Record the current baseline at fixed row counts with wall time, peak memory, query count, and output-equivalence data; use no live records.
- [x] 1.3 Review query plans/indexes for affected MySQL queries and document why any index change is or is not warranted.

## 2. Bounded telephony reporting and sync (P1)

- [x] 2.1 Add regression tests for distinct agent/status options and exact realtime Today/rolling outputs.
- [x] 2.2 Use SQL distinct for call-history option values and database aggregates for Today metrics.
- [x] 2.3 Process rolling call metrics through stable bounded chunks while preserving event-window and duration rules.
- [x] 2.4 Add keyset-paged streaming for historical VICIdial sync with non-null stable ordering and bounded page memory.
- [x] 2.5 Upsert each page before fetching the next; preserve replay safety and do not advance the checkpoint after partial failure.
- [x] 2.6 Test null/empty source IDs, equal timestamps, multi-source ordering, later-page errors, and successful retry.

## 3. Email snapshot and totals scaling (P2)

- [x] 3.1 Add tests for version-2 campaign header and recipient snapshot authentication, tamper rejection, and version-1 compatibility.
- [x] 3.2 Add an additive migration for snapshot version/digests and nullable derived counters with no backfill.
- [x] 3.3 Freeze compact campaign headers and authenticated per-recipient document snapshots while retaining the full approval hash.
- [x] 3.4 Centralize recipient status transitions and atomically maintain sent, failed, skipped, open, and attention counters.
- [x] 3.5 Route send, skip, interruption, cancel, retry, and uncertain-outcome resolution through the transition service.
- [x] 3.6 Reconcile active campaign totals periodically and initialize counters for legacy active campaigns without resending messages.
- [x] 3.7 Verify per-recipient send work no longer hashes/searches the complete manifest or groups all recipient statuses.
- [x] 3.8 Add MySQL concurrency coverage for competing recipient transitions and counter reconciliation.

## 4. Dashboard service extraction (P3)

- [x] 4.1 Add focused tests for shared custom trigger matching, numeric amount handling, and date boundaries.
- [x] 4.2 Extract custom sales rule evaluation, bounded query construction, and result assembly into a cohesive service.
- [x] 4.3 Delegate DashboardStatsService custom-sale paths to the new service and preserve existing result shapes.
- [x] 4.4 Run the existing dashboard sales/report regression suites against the extracted implementation.

## 5. Verification and phase report

- [x] 5.1 Rerun synthetic workloads and compare metrics with the recorded baseline.
- [x] 5.2 Run focused and full PHP suites with no coverage, repository-wide Pint, and strict OpenSpec validation.
- [x] 5.3 Update root specifications, measured benchmark evidence, and this task list from verified behavior.
- [ ] 5.4 Run the email recipient transition race against the isolated MySQL CI service.
- [ ] 5.5 Archive only after local and CI-required validation completes.

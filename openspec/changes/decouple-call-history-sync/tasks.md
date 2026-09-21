## 1. Local persistence and configuration

- [x] 1.1 Add migrations for normalized local telephony call history and per-scope synchronization state with stable unique identity and query indexes.
- [x] 1.2 Add Eloquent models, factories where useful, casts, relationships, and configuration for sync cadence, overlap, chunk size, timeout, retry, and stale thresholds.
- [x] 1.3 Add focused schema/model tests covering unique source identity, campaign isolation, and checkpoint fields.

## 2. Synchronization pipeline

- [x] 2.1 Extend the VICIdial historical provider with bounded range fetching and reusable normalization for both historical source tables.
- [x] 2.2 Implement a call-history synchronization service that resolves mapped server/campaign scope, applies overlap windows, normalizes rows, and chunk-upserts local records.
- [x] 2.3 Implement checkpoint advancement, inserted/updated counters, structured timing logs, failure classification, and preservation of the last successful checkpoint.
- [x] 2.4 Add a unique, non-overlapping `SyncVicidialCallHistoryJob` with bounded timeout, retry/backoff, and safe failure handling.
- [x] 2.5 Add scheduled recent-sync dispatch for eligible scopes without performing remote work in the scheduler request.
- [x] 2.6 Add an administrative `vicidial:sync-call-history` command with server/campaign/from/to options and chunked backfill dispatch/processing.
- [x] 2.7 Add unit/feature tests for initial sync, incremental checkpointing, overlap deduplication, multi-campaign scope, retries/failures, and concurrent refresh protection.

## 3. Local Call History read path

- [x] 3.1 Implement a local Call History query/read service with default recent date scope, selected columns, filters, stable sorting, pagination, user mapping, and disposition mapping.
- [x] 3.2 Update the authenticated Call History API/resource to use local records and expose sync health, stale data, confirmed empty, and unavailable states.
- [x] 3.3 Add authenticated refresh-dispatch and sync-status endpoints with scope authorization and duplicate-job suppression.
- [x] 3.4 Change agent and admin page controllers to render a lightweight shell without invoking the remote provider; preserve submitted-record and live-session behavior.
- [x] 3.5 Preserve authorized deep-link filters such as lead ID/phone and verify campaign isolation in local reads.

## 4. Shared layout and frontend behavior

- [x] 4.1 Remove live VICIdial work from shared layout initialization by using local-only session state and keeping operational polling on agent-specific paths.
- [x] 4.2 Convert Call History rows to asynchronous local API loading with skeleton, loaded, confirmed-empty, stale, unavailable, and retry states.
- [x] 4.3 Add debounced text filters, superseded-request cancellation/ignore behavior, server-side pagination controls, and refresh status polling.
- [x] 4.4 Ensure only the active refresh action is disabled, navigation remains interactive, and responsive/details controls meet existing accessibility and design-token conventions.
- [ ] 4.5 Add/extend browser tests for slow/unavailable VICIdial navigation, sidebar and admin flows, local pagination/filter requests, refresh behavior, console health, and representative viewports.

## 5. Verification and closeout

- [x] 5.1 Run focused PHPUnit coverage for persistence, synchronization, API, page controllers, and regression-sensitive telephony behavior.
- [x] 5.2 Run Pint on modified PHP files and the repository's available frontend build/lint checks.
- [ ] 5.3 Run Playwright validation at 375px, 768px, 1024px, and 1440px, recording navigation timing, remote-request count, local API timing, and browser errors.
- [x] 5.4 Review changed files for authorization, privacy, N+1 queries, bounded queries, stale-data safety, and migration rollback behavior.
- [x] 5.5 Synchronize the OpenSpec artifacts with actual implementation and validation results.
- [ ] 5.6 Run the browser validation, then archive the completed change.

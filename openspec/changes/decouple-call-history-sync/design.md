## Context

The current `RecordsController` and admin `RecordsListController` call `CallHistoryService::getHistoricalHistory()` during the page request. That service opens a per-server VICIdial connection and performs a count, page query, and metadata query over `vicidial_log` and `vicidial_closer_log` before Blade can render. The global authenticated layout also initializes the phone widget, whose session status endpoint can perform live queue polling.

The repository already has a normalized `HistoricalCallRecord`, a campaign/server scope resolver, a read-only VICIdial provider, a database queue configuration, and separate `crm_call_history` and `call_sessions` tables. The new design keeps those domain boundaries while changing the historical source of normal reads to a local synchronized table.

## Goals / Non-Goals

**Goals:**

- Render Call History navigation and the shared CRM shell without waiting for VICIdial.
- Store normalized completed historical calls locally for fast, paginated reads.
- Synchronize recent calls incrementally and backfill older ranges asynchronously.
- Make synchronization idempotent, locked per server/CRM campaign scope, observable, and recoverable.
- Preserve campaign isolation, mapped/unknown agents, raw statuses, disposition mapping, phone behavior, and existing submitted-record history.
- Keep normal filtering, sorting, pagination, and refresh-status requests local to CRM.
- Provide measured instrumentation for remote sync work and local user-path performance.

**Non-Goals:**

- Replacing Supervisor real-time monitoring or historical Reports aggregation.
- Changing the documented VICIdial source columns or fabricating unsupported talk-time values.
- Deleting local calls because a later VICIdial response omits them.
- Adding external packages or changing CRM form-submission semantics.
- Guaranteeing live production latency in automated tests without a configured remote VICIdial server.

## Decisions

### 1. Use separate local telephony tables

Create `telephony_call_histories` for normalized VICIdial rows and `vicidial_call_history_sync_states` for per-server/CRM-campaign checkpoints. `crm_call_history` remains the CRM form-submission table, and `call_sessions` remains the live CRM call lifecycle table.

The telephony row stores only values sourced by the existing provider: server and CRM campaign scope, source table, VICIdial unique ID, campaign/list/lead identifiers, VICIdial user, phone, raw status, raw termination reason, call timestamps, duration, wait seconds, direction, source update time, and sync time. A unique key over server, source table, source unique ID, and CRM campaign preserves the campaign-scoped snapshot boundary while preventing overlap duplicates within a scope. Indexes prioritize `(crm_campaign_id, call_started_at)`, `(crm_campaign_id, vicidial_user, call_started_at)`, phone lookup, status, disposition, and source identity.

Alternative: reuse `crm_call_history`. Rejected because it has required form fields and represents a different business event. Alternative: one global source row without CRM campaign scope. Rejected because every normal read must enforce CRM campaign isolation.

### 2. Reuse normalization and add a sync-oriented provider boundary

Extend the existing provider so its source-table selection and normalization remain in one place. Add a bounded range/incremental fetch used only by the sync service; normal Call History reads never instantiate the remote provider. The sync service resolves each campaign through `CrmCampaignVicidialScopeResolver`, uses all enabled historical mappings on the selected server, fetches bounded windows, normalizes rows, and performs chunked upserts.

Alternative: create a second VICIdial parser for jobs. Rejected because Supervisor, Reports, and synchronization would drift in column and timezone semantics.

### 3. Use checkpoint plus overlap-window synchronization

Each sync state records the last successful source timestamp/identifier, last successful sync time, last failure, status, duration, and inserted/updated counts. Recent sync starts slightly before the checkpoint (configurable default five minutes) and ends at the current bounded time. The source unique key makes the overlap idempotent and protects late-arriving records. A successful batch advances the checkpoint only after all its upserts succeed; failures preserve the previous checkpoint.

Recent synchronization runs every minute by dispatching unique jobs. Historical backfill accepts server/campaign/from/to options, chunks date ranges, and uses the same service and job path. It does not run in a user request.

### 4. Lock at dispatch and execution

`SyncVicidialCallHistoryJob` implements `ShouldBeUnique` with a unique key containing server and CRM campaign scope, and uses `WithoutOverlapping` with an explicit expiration as a defense against duplicate execution. The refresh endpoint returns an in-progress response when the same scope already has a queued/running job. Scheduler dispatch is additionally protected with a single-server schedule lock where the configured cache driver supports it.

This follows Laravel's cache-backed uniqueness and overlap primitives and avoids creating one remote request per user click.

### 5. Make local reads the only normal Call History path

`RecordsController` and `RecordsListController` render a lightweight shell and pass only campaign/deep-link context. A local query service builds an indexed Eloquent query with a default recent date window, server-side pagination, stable newest-first ordering, filters, and a selected-column projection. The authenticated API serializes the same local DTO/resource contract. User mapping is batch-loaded by `vici_user` including soft-deleted users; disposition labels are resolved from existing CRM disposition data.

The API exposes sync health separately from row availability. If the remote sync is delayed, the local rows remain readable and the response reports stale/delayed status instead of converting the result to an error or empty dataset.

### 6. Separate shared-layout local status from remote operational polling

The shared phone widget will request local session state only during layout initialization. Queue count and other live VICIdial operations remain on the agent-specific operational path or explicit user actions. This removes remote telephony work from sidebar/topbar rendering without removing telephony controls.

### 7. Use an async, resilient Call History interface

The page renders headings, filters, table headers, and stable skeleton rows immediately. Alpine fetches the local API after paint, debounces text filters, cancels or ignores superseded requests, and replaces only the table/status region. Refresh dispatches a job and disables only its own button. Loaded, confirmed-empty, delayed, unavailable, and retry states use semantic text and `aria-live`/busy state; the table remains horizontally scrollable on narrow screens and details remain keyboard-accessible.

These choices follow the UI UX Pro Max guidance selected for this flow: skeleton feedback, accessible error announcements, debounced high-frequency input, responsive overflow handling, and no disabled global navigation.

### 8. Instrument before/after behavior

Structured logs record sync scope, window, rows received/in-scope/inserted/updated, fetch/parse/upsert/total durations, and sanitized failure classification. Application timing around page controllers and local API reads is captured in tests or request instrumentation. Browser validation records navigation transition, local API response, remote request count, console errors, and representative viewport behavior. Live-source limitations are reported explicitly.

## Risks / Trade-offs

- [Initial local history is empty] → Provide the backfill command and clear “syncing/no local data yet” status; do not synchronously fall back to a full VICIdial download.
- [A VICIdial server has missing or variant log columns] → classify the sync failure, preserve existing local rows/checkpoint, and expose a recoverable status without guessing columns.
- [A sync job runs longer than its cadence] → use unique/overlap locks, bounded source windows, chunked upserts, explicit job timeout/retry/backoff, and stale health metadata.
- [The same source call is mapped to unusual multiple CRM campaigns] → keep campaign-scoped rows and document the snapshot identity in the schema; each query still requires its CRM campaign scope.
- [Remote calls expose sensitive values] → never log credentials or full phone numbers; use existing masking/presentation rules and sanitized diagnostics.
- [File/database cache is not shared across workers] → document that production multi-worker deployments must use a shared lock-capable cache for uniqueness and scheduler locks.
- [Existing tests assume live provider reads] → retain provider tests for sync input and replace page/API mocks with local repository fixtures, while keeping provider tests for source normalization.

## Migration Plan

1. Deploy migrations, models, sync service/job/command, local read service, status/refresh endpoints, and configuration together.
2. Run the scheduler/queue worker and execute the backfill command for the required historical range.
3. Switch page/API reads to local storage; existing CRM submission and live-session tables are untouched.
4. Monitor sync health, failed jobs, local response duration, and remote request counts.
5. Roll back code if needed; retain the new tables/data because rollback does not mutate existing tables and is safe for a later re-deploy.

## Open Questions

- The configured production VICIdial server and actual row volume determine whether offset pagination remains adequate; the first implementation will retain Laravel length-aware pagination and add indexes, with cursor pagination left as a measured follow-up if deep pages are demonstrably slow.
- The current app has no dedicated Record List row-to-history link beyond the admin Call History tab. Existing deep-link `lead_id`/phone query support will be preserved where present, and a concrete row-link integration will be added only where an existing record view exposes that action.

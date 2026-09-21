## Why

Call History currently waits for live VICIdial historical database queries before its page can render. This couples navigation and filtering latency to an external system and can make the shared CRM shell appear frozen when VICIdial is slow or unavailable.

## What Changes

- Add a local, campaign-scoped telephony call-history store separate from CRM form-submission history.
- Move VICIdial historical retrieval into incremental background synchronization with overlap protection, idempotent upserts, retry handling, and persisted sync health.
- Add scheduled recent synchronization and an administrative, chunked historical backfill command.
- Make normal Call History page navigation render without contacting VICIdial.
- Make the authenticated Call History API read locally with indexed server-side pagination, filtering, sorting, and campaign isolation.
- Add non-blocking refresh dispatch and sync-status reporting with duplicate-job protection.
- Remove live historical and unnecessary remote session polling from shared layout rendering while preserving agent telephony behavior.
- Load Call History rows asynchronously with accessible loading, empty, stale, unavailable, retry, and responsive table states.
- Preserve stable VICIdial identifiers, raw status/disposition values, CRM user mappings, phone privacy behavior, and the separate submitted-records tab.
- Add instrumentation and automated coverage for navigation latency, local query behavior, synchronization, deduplication, failures, scope isolation, and UI flows.

## Capabilities

### New Capabilities

- `call-history-sync`: Background synchronization, checkpoints, locking, retries, backfill, and sync-health reporting for local telephony history.

### Modified Capabilities

- `vicidial-call-history`: Change the authoritative read path from live on-demand VICIdial queries to the locally synchronized store while preserving the normalized API and campaign-scoped presentation contract.

## Impact

- Laravel migrations, models, services, jobs, console scheduling, controllers, requests, resources, routes, and configuration under `app/`, `database/`, `routes/`, and `config/`.
- Call History Blade/Alpine views and related frontend assets under `resources/views/` and `resources/js/`.
- Existing `VicidialHistoricalCallProvider`, campaign scope resolver, user/disposition mappings, queue backend, and logging conventions will be reused or extended.
- Existing `crm_call_history`, `call_sessions`, Supervisor real-time behavior, and historical report aggregation remain separate.
- No new external dependency is required.

## 1. Mapping data model and migration

- [x] 1.1 Create the `campaign_vicidial_mappings` migration with foreign keys, enabled/status metadata, indexes, and the composite uniqueness constraint.
- [x] 1.2 Backfill one active mapping per existing CRM-owned VICIdial server using the legacy server campaign code without changing legacy columns.
- [x] 1.3 Add `CampaignVicidialMapping` model/factory and `Campaign`/`VicidialServer` relationships with casts and safe activity-log fields.

## 2. Shared scope and catalog services

- [x] 2.1 Implement `VicidialCampaignScope` with live/historical code selection, membership checks, and safe routing serialization.
- [x] 2.2 Implement cached `CrmCampaignVicidialScopeResolver` using existing server default/priority resolution and strict CRM/server ownership checks.
- [x] 2.3 Add cache invalidation after mapping, campaign, and server configuration changes.
- [x] 2.4 Add server-bound `campaigns_list` catalog retrieval/parsing through the existing Non-Agent API transport, including safe failure handling.

## 3. Administrator mapping workflow

- [x] 3.1 Add mapping request validation and controller actions for server-bound catalog loading and atomic mapping replacement.
- [x] 3.2 Add protected admin routes for campaign choices and mapping updates.
- [x] 3.3 Add the Campaigns admin mapping panel with accessible multi-select checkboxes, search, count, select-all, clear-all, stale/unavailable states, and responsive layout.
- [x] 3.4 Ensure selected server/campaign ownership and remote catalog membership are enforced server-side; reject zero mappings without fallback.

## 4. Supervisor and Reports integration

- [x] 4.1 Pass the resolved scope into Supervisor snapshot construction and include mapped server/campaign routing context.
- [x] 4.2 Filter/deduplicate remote agent, performance, call, queue, session, CRM call, and disposition rows by mapped campaign codes before aggregation.
- [x] 4.3 Update real-time Reports rolling/today queries to use the same scope and expose permitted campaign metadata.
- [x] 4.4 Update Historical Reports parsing, secondary campaign filtering, campaign breakdown, agent/disposition merges, and weighted raw-total rates.
- [x] 4.5 Preserve softphone/session campaign routing and existing server endpoint precedence while preventing unmapped campaign leakage.

## 5. Automated and browser verification

- [x] 5.1 Add migration/model/resolver tests for one-to-many mappings, duplicate rejection, wrong-server rejection, disabled/stale handling, cache invalidation, and empty mappings.
- [x] 5.2 Add catalog/admin feature tests for server isolation, multi-selection validation, catalog failure, and mapping persistence.
- [x] 5.3 Add Supervisor and Reports tests for mapped-agent inclusion, unrelated-campaign exclusion, deduplication, aggregate counts, breakdowns, and the 100/50 + 900/90 = 14% weighted-rate case.
- [x] 5.4 Run Pint, focused PHPUnit tests, frontend build, Playwright admin/Supervisor/Reports flows at representative responsive widths, and strict OpenSpec validation.

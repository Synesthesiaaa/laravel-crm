## Why

CRM campaigns currently resolve a VICIdial server but treat the CRM campaign code as the only VICIdial campaign code. That hides agents, calls, queues, and historical activity when one business campaign legitimately spans several VICIdial campaigns, and it makes broad server feeds a data-isolation risk.

## What Changes

- Add a normalized CRM-campaign-to-VICIdial-campaign mapping that is anchored to the CRM campaign and its selected VICIdial server.
- Preserve existing server configuration and single-campaign behavior while safely backfilling legacy campaign-code mappings.
- Centralize resolution of the selected CRM campaign, server, enabled campaign codes, and mapping health in one reusable scope object/service.
- Add administrator controls for selecting multiple campaigns belonging only to the configured server, with validation, duplicate protection, empty-state feedback, and cache invalidation.
- Update Supervisor live-agent, active-call, queue, and status aggregation to filter server-wide data by the resolved campaign set, deduplicate agents, and expose routing context.
- Update historical Reports to use the same permitted campaign set, aggregate raw totals before calculating rates, merge agent/disposition activity, and optionally narrow only to mapped VICIdial campaigns.
- Enforce backend filtering so unmapped campaigns and wrong-server campaigns cannot leak through API responses or frontend display logic.
- Add mapping, isolation, migration, aggregation, failure, and weighted-rate regression coverage.

## Capabilities

### New Capabilities

- `crm-campaign-vicidial-mapping`: Normalized mapping, administration, validation, stale/disabled status, and shared scope resolution.

### Modified Capabilities

- `campaign-scoped-vicidial-supervision`: Supervisor data uses all mapped VICIdial campaigns for the selected CRM campaign.
- `telephony-operations-and-reporting`: Supervisor and historical report aggregation share the mapped campaign scope and support permitted campaign breakdowns.

## Impact

- New mapping migration/model/relationships and legacy-data backfill.
- Campaign/server administration controllers, requests, Blade forms, and campaign-choice endpoint behavior.
- Telephony scope resolution, Supervisor operational aggregation, historical reporting, API response contracts, and report filtering.
- Focused PHPUnit feature/unit tests, migration compatibility, and browser validation for the administrator mapping flow and Supervisor/Reports presentation.
- No new dependency is required; existing Laravel, Eloquent, Alpine, Tailwind, VICIdial transport, caching, and authorization conventions remain in use.

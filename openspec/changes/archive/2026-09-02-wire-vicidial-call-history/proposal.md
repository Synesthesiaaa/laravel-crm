## Why

The CRM's Call History currently shows locally created `call_sessions`, which only represent calls observed through the CRM dialing/webhook workflow. That omits legitimate historical VICIdial calls and exposes CRM lifecycle status instead of the authoritative telephony status, agent, phone number, disposition, timestamp, and duration. This change makes historical VICIdial call logs the source of truth while preserving the existing CRM campaign-to-server-to-multiple-campaign security boundary.

## What Changes

- Add a read-only, on-demand historical call provider backed by the mapped VICIdial server's `vicidial_log` and `vicidial_closer_log` tables.
- Normalize outbound and inbound/closer rows into a stable `HistoricalCallRecord` representation without fabricating unavailable values.
- Resolve the selected CRM campaign through the existing centralized scope resolver and include only its enabled historical VICIdial campaign mappings.
- Associate rows to CRM users by stable `vici_user` identifiers, retain unmatched VICIdial users, and preserve disabled-user history.
- Add server-side filtering, sorting, and pagination for date range, agent, phone, status, disposition, and mapped VICIdial campaign.
- Expose a stable API/resource contract with source availability states that distinguish confirmed empty results from remote database failures.
- Update the agent and supervisor/admin Call History views to show date/time, agent, phone, status, disposition, duration, campaign, and authorized optional details.
- Preserve existing phone masking behavior and keep CRM form submission history separate from telephony history.
- Add automated coverage for normalization, campaign isolation, stable user mapping, unknown agents/dispositions, duration/date handling, filters, pagination, and remote failures.

## Capabilities

### New Capabilities

- `vicidial-call-history`: Authoritative, campaign-scoped historical call retrieval, normalization, API behavior, and Call History presentation.

### Modified Capabilities

None.

## Impact

- Laravel services, controllers, routes, models/value objects, and Blade views under `app/`, `routes/`, and `resources/views/`.
- The existing `VicidialServer` database credentials become the per-server read connection for historical logs; no local synchronization table or new dependency is required.
- The existing CRM campaign mappings, `User.vici_user` mapping, disposition codes, theme tokens, table components, and authorization/privacy conventions are reused.
- Tests will use mocked remote connections/providers and fixture rows; production verification still requires a configured VICIdial server and a known historical date.

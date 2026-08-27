## Context

The application stores CRM campaigns in `campaigns` and associates VICIdial servers through `vicidial_servers.campaign_code`. The server association is already the Supervisor routing boundary, but the same CRM code is currently reused as the only VICIdial campaign filter. Supervisor and historical reporting already use server-wide VICIdial feeds, so the missing piece is a single backend scope that filters those feeds to an administrator-defined set.

VICIdial exposes campaign metadata through the existing Non-Agent API `campaigns_list` function. There is no synchronized local VICIdial campaign catalog in this repository, so the administrator selector will load permitted choices from the selected server and retain saved codes when a later metadata refresh marks them unavailable.

## Goals / Non-Goals

**Goals:**

- Store one or many normalized VICIdial campaign-code mappings per CRM campaign/server.
- Backfill the legacy server campaign code without removing or repurposing existing routing fields.
- Resolve one active server and its enabled mapped codes once for Supervisor and Reports.
- Filter all server-wide rows in Laravel before aggregation or JSON serialization.
- Keep historical visibility for enabled mappings whose VICIdial metadata is stale/unavailable, while excluding disabled mappings from live scope.
- Provide an accessible, searchable checkbox selector with count, select-all, and clear-all behavior.

**Non-Goals:**

- Changing the floating softphone's current VICIdial campaign/session selection.
- Supporting one CRM campaign across multiple VICIdial servers in the same snapshot.
- Introducing list-level or lead-level mapping without evidence that current reporting requires it.
- Automatically renaming, deleting, or remapping a VICIdial campaign when the remote catalog changes.
- Rewriting the existing realtime diagnostics change or adding a new package.

## Decisions

### 1. Use an explicit mapping model and preserve legacy server ownership

Create `campaign_vicidial_mappings` with `campaign_id`, `vicidial_server_id`, `vicidial_campaign_code`, `is_enabled`, `status`, `last_seen_at`, and timestamps. Add a composite unique key on campaign, server, and code. Keep `vicidial_servers.campaign_code` as the existing CRM ownership key because changing it would affect server resolution and existing telephony configuration. Validation only permits a server whose `campaign_code` matches the CRM campaign being edited.

The migration inserts one enabled `active` mapping for each existing server row whose campaign code matches an existing CRM campaign. The legacy field remains available for rollback and non-reporting telephony behavior.

### 2. Resolve through one immutable scope object

Add `CrmCampaignVicidialScopeResolver` and `VicidialCampaignScope`. The resolver loads the active CRM campaign, the repository-selected active server, and mappings for that exact campaign/server. The scope exposes CRM campaign metadata, server metadata, all enabled codes for historical filtering, live codes, and safe serialized routing context. Resolution is cached briefly by campaign ID and invalidated after mapping or server/campaign changes.

An explicit empty mapping is a valid incomplete configuration: it returns no permitted VICIdial campaign codes and never becomes `---ALL---`. This is distinct from a legacy database that has not yet been backfilled; the migration handles that state before the new code is used.

### 3. Fetch server-wide once, then enforce the scope in backend parsers

Supervisor continues using the existing bounded batch of `logged_in_agents`, `agent_stats_export`, and `call_status_stats`, and Reports continues using its existing historical batch. Requests remain server-wide where that avoids one request per mapping, but every parser receives the resolved allowed-code set and drops rows whose campaign/campaign_id is absent or unrelated. Deduplication uses VICIdial username/user ID, and the actual current VICIdial campaign is retained on each agent record.

CRM call/session and disposition queries use `whereIn` with the same scope codes. Historical campaign rows are aggregated from raw totals; answer/contact/conversion rates are calculated from summed numerators and denominators. A permitted secondary VICIdial campaign filter is intersected with the scope and cannot broaden it.

### 4. Make catalog loading an admin-only, server-bound operation

Add a super-admin route for campaign choices under the selected CRM campaign. It verifies that the requested server belongs to that CRM campaign, then invokes a small catalog service over the existing Non-Agent transport using `campaigns_list`, `stage=pipe`, and `header=YES`. The response contains only campaign code, name, and active state. A short cache reduces repeated selector loads; no credentials or raw remote payload is returned.

Mapping saves validate the selected server ownership, distinct code syntax, and remote catalog membership when the catalog is available. If the server is unreachable, the save fails with an actionable validation error rather than silently accepting a code that cannot be verified. Existing saved stale codes are shown as unavailable so administrators can repair them.

### 5. Put configuration in the existing Campaigns admin screen

Keep VICIdial server connection editing in its existing screen. Add a mapping panel per CRM campaign with a server select limited to that CRM campaign's server records and an Alpine-powered checkbox list. The panel displays the selected count, searchable choices, select-all/clear-all controls, inline errors, and saved unavailable mappings. It uses visible labels, keyboard-focus styles, semantic status text, and responsive wrapping consistent with the current design tokens.

### 6. Return routing context to Supervisor and Reports

Add mapped server and permitted campaign metadata to the existing routing/filter response shape. Supervisor remains CRM-campaign-first and defaults to all live mapped campaigns. Reports returns the mapped set and campaign breakdown; its optional secondary filter is validated against that set. No frontend code reconstructs scope or hides unrelated data.

## Risks / Trade-offs

- [Remote `campaigns_list` permissions differ by deployment] → Show a catalog-unavailable validation message, preserve stale saved mappings, and keep report responses safe rather than falling back to all campaigns.
- [A mapping changes while a browser poll is in flight] → Backend resolves the current scope on each request and short cache invalidation occurs immediately after saves; the response includes the resolved codes for diagnostics.
- [Historical mappings have no effective-date history] → Use the current enabled mapping, document that limitation in the capability contract, and do not invent historical configuration state.
- [Existing tests create servers without explicit mapping rows] → Update affected fixtures/tests to create the legacy-equivalent mapping; production upgrade safety comes from the backfill migration.
- [VICIdial headers differ or omit campaign columns] → Treat rows without a reliable campaign identifier as out of scope for a mapped request instead of leaking them; preserve source health diagnostics.
- [Multiple active server rows exist for a CRM campaign] → Reuse the existing default/priority selection and only allow mappings for that selected server in the resolved scope.

## Migration Plan

1. Add the mapping table and backfill rows from existing `vicidial_servers.campaign_code` values.
2. Deploy model relationships, resolver, catalog endpoint, mapping UI, and filtered Supervisor/Reports services.
3. Run focused migration, mapping, isolation, Supervisor, Reports, and weighted-rate tests, then build the frontend.
4. An administrator refreshes each server's catalog and repairs mappings reported as stale/unavailable.
5. Rollback is code-version rollback plus the reversible mapping-table migration; the legacy server field remains untouched.

## Open Questions

- The application has no mapping-history table, so historical reports initially use the current enabled mapping. A later requirement can add effective-dated mappings if audit evidence requires it.
- In-group mappings are not introduced here because the existing reports expose campaign rows and the current schema has no authoritative CRM-to-in-group catalog. Queue filters remain based on the existing server report signals.

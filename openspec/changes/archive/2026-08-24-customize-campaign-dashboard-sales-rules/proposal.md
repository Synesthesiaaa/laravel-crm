## Why

Dashboard layouts are already stored per campaign, but administrators must first change the application's active campaign to edit them, and dashboard sales are still inferred from numeric fields marked in Field Logic. Campaigns need an explicit, auditable way to decide which form tag values count as sales and which optional amount field contributes value, without mixing reporting policy into the form schema.

## What Changes

- Add a campaign selector to dashboard customization that edits a chosen campaign without changing the administrator's globally active campaign.
- Extend each campaign's existing dashboard JSON configuration with explicit per-form sales rules.
- Allow each configured form to define one optional numeric amount field and multiple tag conditions with multiple accepted values.
- Match accepted values after trimming and case normalization, use OR semantics within a form, and count a matching submission at most once.
- Drive every sales-derived dashboard result from the same configured matches, including Sales, Top Agent, agent leaderboard, per-form breakdown, and campaign sales reports.
- Preserve the existing marked sale-amount behavior for campaign dashboards that have never saved custom sales rules, with an explicit reset action for returning to that legacy mode.
- Validate campaign ownership, registered fields, supported tag types, numeric amount fields, input limits, and unknown nested keys before saving the layout and rules atomically.
- Surface stale or invalid saved references as actionable admin warnings while keeping user dashboards available.

## Capabilities

### New Capabilities

- `campaign-dashboard-sales-rules`: Configure, validate, persist, reset, and evaluate campaign-specific form tag rules and optional sale amounts.

### Modified Capabilities

- `admin-dashboard-layout`: Select and save a campaign's complete dashboard configuration without first changing the active application campaign.
- `field-sale-attribution`: Use explicit campaign dashboard tag rules for sales attribution when custom mode is configured, while preserving legacy marked-field attribution for existing dashboards.
- `daily-campaign-report`: Keep daily and month-to-date sales-derived report counts and amounts consistent with the campaign's configured sales attribution mode.

## Impact

- Affected backend: dashboard configuration persistence, admin dashboard controller/request validation, sales-rule resolution, dashboard aggregation, cache invalidation, and dashboard update broadcasting.
- Affected frontend: the admin dashboard customization form and user-facing sales-derived dashboard labels, totals, breakdowns, warnings, and empty states.
- Affected tests: service, request, authorization, campaign-isolation, dashboard rendering, JavaScript interaction, and Playwright responsive-flow coverage.
- Data storage: reuse `dashboard_layouts.layout`; no new table or dependency is required.

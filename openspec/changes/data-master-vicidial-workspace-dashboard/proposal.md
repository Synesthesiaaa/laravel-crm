## Why

Data Master form selection currently performs a hard page reload, which tears down the persistent Vicidial widget and can lose the active dialer session. Users also need a faster way to search records and work with the Quick Form and Vicidial panels together, while administrators need a controlled way to publish a consistent dashboard layout to users.

## What Changes

- Preserve the Vicidial session when loading a different Data Master form by routing the selector through the existing soft-navigation boundary.
- Add server-side Data Master search across the selected form's safe database columns, including query-preserving pagination and clear-search behavior.
- Add an optional, persisted split workspace that opens Quick Form and Vicidial side by side on desktop while retaining the existing floating behavior on small screens.
- Add an admin-only, campaign-scoped dashboard layout editor for showing/hiding and ordering existing user dashboard sections.
- Broadcast applied dashboard layout changes so active user dashboards refresh through soft navigation without a manual browser reload.

## Capabilities

### New Capabilities

- `data-master-navigation-search`: Preserve Data Master widget state during form navigation and provide searchable, paginated records.
- `widget-split-workspace`: Provide a persisted desktop split view for the Quick Form and Vicidial widgets.
- `admin-dashboard-layout`: Allow Admin and Super Admin users to publish a campaign-scoped visibility/order configuration for the user dashboard.

### Modified Capabilities

- `platform-stabilization`: Extend soft-navigation behavior to marked GET forms so persistent telephony widgets survive form selection.
- `dashboard-live-updates`: Include published dashboard layout changes in the campaign-scoped dashboard refresh path.

## Impact

- Laravel routes, controllers, services, model/migration, validation, Blade views, Alpine/JavaScript widget state, and dashboard broadcast events.
- New `dashboard_layouts` persistence keyed by campaign; no external dependencies are required.
- Existing `user_widget_layouts` API is extended with a `workspace` layout key for the split-view preference.
- Affected validation includes role authorization, campaign scoping, safe dynamic-table search columns, and normalized dashboard section keys/order.

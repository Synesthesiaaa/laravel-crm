## Context

The application renders authenticated pages inside a shared Blade shell. The Vicidial iframe and Quick Form iframe live outside `#main-layout`, and `resources/js/soft-navigate.js` swaps only that main layout so telephony state can survive normal navigation. The Data Master form selector currently uses a normal GET form, bypassing that lifecycle boundary and causing a full browser reload.

Data Master records are read from campaign-configured tables through `DataMasterService`, so search must be applied only to an allowlisted table and its actual schema columns. The two floating widgets already persist their individual geometry through `user_widget_layouts`; the split mode can extend that persistence without introducing another storage system.

The user dashboard is server-rendered and already subscribes to a campaign-scoped private Echo channel for data invalidation. Dashboard layout is currently hard-coded in Blade and there is no shared admin configuration record. The new configuration must be campaign-scoped, admin-only to write, and read by all users of that campaign.

## Goals / Non-Goals

**Goals:**

- Keep the active Vicidial iframe and session runtime alive when an administrator selects another Data Master form.
- Search Data Master records server-side without interpolating untrusted column names into SQL.
- Provide a persisted desktop split mode for the Quick Form and Vicidial widgets with a safe small-screen fallback.
- Let Admin and Super Admin roles publish dashboard section visibility and order for the currently selected campaign.
- Refresh open user dashboards after an applied layout change through the existing soft-navigation and dashboard-channel infrastructure.

**Non-Goals:**

- Do not change Vicidial credentials, campaign resolution, iframe URL construction, or logout contracts.
- Do not add a client-side data grid or load all Data Master records into the browser.
- Do not let regular users override the published dashboard layout.
- Do not make chart date ranges, KPI definitions, colors, or dashboard content editable in this change.
- Do not add a third-party drag-and-drop or state-management dependency.

## Decisions

### 1. Marked GET forms use the existing soft-navigation boundary

Add a `data-soft-nav` marker to the Data Master selector and teach the existing delegated submit handler to serialize marked GET forms into a URL before calling the existing `softNavigate` function. The current browser navigation remains the fallback when the shell, Alpine, or fetched response is unavailable.

This keeps the widget DOM outside `#main-layout`, preserves the existing lifecycle hooks, and also gives future filter forms an opt-in path without changing every GET form in the application. The implementation will not intercept POST forms.

### 2. Search is performed in the Data Master service

Extend `DataMasterService::getRecords` with an optional trimmed search term. When present, retrieve the selected table's column listing, keep only valid string column names, and add a grouped `LIKE` condition for each column. The controller passes `request->query('search')` after length validation and the paginator uses `withQueryString()` so both `type` and `search` survive page changes.

This preserves the controller's existing table allowlist and prevents user input from becoming a SQL identifier. If schema inspection or the query fails, the service returns the same empty paginator behavior already used for invalid tables.

### 3. Split mode is a shared, per-user workspace preference

Add a `workspace` allowed key to `WidgetLayoutUpdateRequest`, accepting only `layout.splitScreen`. Create a small `resources/js/widgets/workspace.js` module that loads and persists this preference through the existing layout API, exposes a `window.crmWidgetWorkspace` controller, and dispatches a `crm-widget-workspace` browser event.

The phone and Quick Form components listen to that event and apply a shared desktop geometry: two panels with equal available width, a bounded viewport margin, both open, and their existing internal controls/iframes intact. Their normal floating geometry remains unchanged when split mode is disabled or the viewport is below the desktop breakpoint. The preference is best-effort like the existing widget layout persistence; failure falls back to the local default.

### 4. Dashboard layout is a dedicated campaign-scoped record

Create `dashboard_layouts` with a unique `campaign_code` and JSON `layout`. `DashboardLayoutService` owns the default section catalog, normalization, ordering, visibility, and persistence. The service accepts only known section keys and guarantees every known section has one normalized order position, so malformed or stale saved JSON cannot hide the entire page.

The admin dashboard renders an editor for the currently resolved campaign. It submits an ordered list and visible list to an admin-only POST action. The user dashboard wraps its existing sections in a flex column and applies the normalized order/visibility without rewriting the data aggregation services.

Alternatives considered:

- A single `system_settings` JSON blob was rejected because campaign scope and concurrent updates would be opaque.
- Copying layout rows to every user was rejected because it duplicates global configuration and makes publication non-atomic.
- Replacing the Blade dashboard with a client-rendered component was rejected because all existing KPI/report data is already server-rendered and tested.

### 5. Published dashboard layouts use the existing campaign channel

Add `DashboardLayoutUpdated` as a `ShouldBroadcastNow` event on `dashboard.{campaign}` with only campaign, action, and timestamp metadata. Extend the existing Echo dashboard subscription to listen to the layout event as well as data events. The existing debounced `crmSoftNav.refresh()` path then reloads the current URL and receives the new server-rendered layout.

This provides immediate updates when Reverb is available and retains the existing bounded polling fallback when it is not. The channel authorization remains campaign-scoped; no layout data is broadcast to clients.

### 6. Dashboard section ordering stays in the existing Blade view

Keep the current dashboard sections and data markup in `resources/views/dashboard.blade.php`, but wrap each configurable section with a section key and `order` style. This limits the change to layout composition, preserves all current forms/modals/charts, and avoids a risky partial extraction across a large established view.

## Risks / Trade-offs

- [A marked GET form could receive an unexpected non-HTML response] → The soft-navigation function already falls back when the main layout is missing or the response is a redirect; the form remains a normal GET fallback on fetch failure.
- [Searching every column can be expensive on wide tables] → Search is paginated, scoped to one selected table, bounded to a short term, and only runs when the user supplies a term; future indexing can be added independently.
- [A saved split layout may not fit a smaller viewport] → Split mode is applied only above the desktop breakpoint and all panel sizes are computed from current viewport dimensions on resize.
- [An invalid dashboard JSON record could hide content] → `DashboardLayoutService` normalizes against a fixed allowlist and restores missing sections to defaults.
- [Broadcasting may be disabled or unavailable] → The existing dashboard fallback timer still performs a soft refresh, and the layout is persisted before the redirect response is returned.

## Migration Plan

1. Run the new `dashboard_layouts` migration; existing users receive the default dashboard because no rows exist yet.
2. Deploy the backend service/controller/event changes and the frontend soft-navigation/workspace changes.
3. Verify Data Master form switching with an active Vicidial session, then verify split mode at desktop and narrow widths.
4. Apply a dashboard layout as Admin and verify another user on the same campaign receives the new order/visibility; verify another campaign remains unchanged.
5. Roll back by disabling the new admin route/UI and reverting the migration/event code. The migration down method removes only the new table; existing widget and dashboard data is untouched.

## Open Questions

None. The approved scope is visibility/order only, with Admin and Super Admin as the writing roles and campaign-scoped publication.

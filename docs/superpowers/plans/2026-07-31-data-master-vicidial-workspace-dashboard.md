# Data Master, Vicidial Workspace, and Admin Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Preserve active Vicidial sessions during Data Master form navigation, add Data Master search, provide a persisted Quick Form/Vicidial split workspace, and let admins publish campaign-scoped dashboard section layouts to users.

**Architecture:** Reuse the existing soft-navigation boundary for marked GET forms and the existing per-user widget-layout API for a shared workspace preference. Store normalized dashboard section configuration in a new campaign-scoped table, render it around the existing server-rendered dashboard sections, and use a campaign-scoped broadcast event to trigger the dashboard's existing debounced soft refresh.

**Tech Stack:** Laravel 12 / PHP 8.5, Blade, Alpine.js 3, MySQL production with SQLite-compatible tests, Vite, Node test runner, Laravel Echo/Reverb, PHPUnit 11, Pint.

## Global Constraints

- Preserve the existing Vicidial campaign resolution, session endpoints, iframe URL contract, and logout behavior.
- Search dynamic Data Master tables only through tables already allowed by campaign configuration and column names returned by the schema.
- Only Admin and Super Admin roles may publish dashboard layouts; regular users only consume the published configuration.
- Do not add dependencies; use native browser events/controls and existing persistence/broadcasting infrastructure.
- Every changed PHP file must be formatted with `vendor/bin/pint --dirty --format agent` before completion.

---

### Task 1: Data Master search and soft-navigation preservation

**Files:**
- Modify: `app/Services/DataMasterService.php` (`getRecords` search parameter and safe grouped LIKE query)
- Modify: `app/Http/Controllers/Admin/DataMasterController.php` (validate/forward bounded `search` query)
- Modify: `resources/views/admin/data_master.blade.php` (search controls, clear link, query-preserving empty state, marked selector form)
- Modify: `resources/views/components/table/pagination.blade.php` or paginator call site if needed to preserve query strings
- Modify: `resources/js/soft-navigate.js` (marked GET submit interception)
- Test: existing `tests/Feature/Admin/ExtractionExportTest.php` or a new PHPUnit feature test under `tests/Feature/Admin/`

**Interfaces:**
- `DataMasterService::getRecords(string $tableName, array $allowedTables, int $perPage = 20, ?string $search = null): LengthAwarePaginator`
- Marked forms use `data-soft-nav` and only GET submissions are intercepted.

- [ ] Write tests that assert a matching search term filters a configured Data Master table, no matches render the empty state, pagination retains `type` and `search`, and an overlong search term is rejected or normalized without broadening the query.
- [ ] Run the focused test file and confirm the new assertions fail before implementation.
- [ ] Add the service query with a trimmed, bounded search term and `Schema::getColumnListing`; use a grouped `where(column, 'like', '%term%')` for validated columns and retain the existing empty paginator fallback.
- [ ] Pass the query through `DataMasterController::index`, render the search field with the selected value, add a clear link preserving the selected form, and call `withQueryString()` on the returned paginator if the shared component does not already do so.
- [ ] Update `soft-navigate.js` with a delegated submit listener that builds a same-origin URL from marked GET forms, invokes `softNavigate`, and falls back to `HTMLFormElement.prototype.submit` when the soft-navigation boundary is unavailable.
- [ ] Run the focused test file again and inspect the response HTML for search text, result rows, and pagination URLs.

### Task 2: Persisted split workspace

**Files:**
- Modify: `app/Http/Requests/Api/WidgetLayoutUpdateRequest.php` (allow `workspace.splitScreen`)
- Modify: `resources/js/app.js` import order if required
- Create: `resources/js/widgets/workspace.js`
- Modify: `resources/js/phone-widget.js` (workspace listener and split geometry)
- Modify: `resources/js/quick-form-widget.js` (workspace listener and split geometry)
- Modify: `resources/views/partials/phone-widget.blade.php` (split control/root style binding)
- Modify: `resources/views/partials/quick-form-widget.blade.php` (split control/root style binding)
- Modify: `resources/css/app.css` (split root/panel rules and desktop media query)
- Modify: `tests/JavaScript/widgets/layout-manager.test.js` or create `tests/JavaScript/widgets/workspace.test.js`
- Modify: `tests/Feature/Api/WidgetLayoutApiTest.php`

**Interfaces:**
- `window.crmWidgetWorkspace.isSplitScreen(): boolean`
- `window.crmWidgetWorkspace.setSplitScreen(enabled: boolean): void`
- `window.crmWidgetWorkspace.toggle(): void`
- Browser event `crm-widget-workspace` with `{ splitScreen: boolean }` detail.

- [ ] Add Node tests for split state normalization and narrow viewport fallback, and PHPUnit tests proving `workspace` is accepted while unknown widget keys and non-boolean split values remain invalid.
- [ ] Run the focused JS/API tests and confirm the new assertions fail before implementation.
- [ ] Implement `workspace.js` using `createLayoutPersistence({ widgetKey: 'workspace' })`, load the saved boolean after Alpine initialization, dispatch events on changes, and fail silently to the default when persistence is unavailable.
- [ ] Import the workspace module before Alpine starts; wire both widgets to listen for the event, set `open`, and calculate two equal desktop panel regions without changing iframe URLs or Vicidial session methods.
- [ ] Add Split view/Exit split view buttons to both widget headers and CSS that hides launchers only in split mode, keeps normal floating styles outside split mode, and reverts to normal layout below the desktop breakpoint.
- [ ] Run the focused Node/API tests and build the frontend bundle.

### Task 3: Dashboard layout persistence and admin editor

**Files:**
- Create: `database/migrations/<timestamp>_create_dashboard_layouts_table.php`
- Create: `app/Models/DashboardLayout.php`
- Create: `app/Services/DashboardLayoutService.php`
- Create: `app/Http/Requests/Admin/DashboardLayoutUpdateRequest.php`
- Modify: `app/Http/Controllers/Admin/AdminDashboardController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/admin/dashboard.blade.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`
- Test: `tests/Unit/Services/DashboardLayoutServiceTest.php`
- Test: `tests/Feature/Admin/AdminDashboardLayoutTest.php`

**Interfaces:**
- `DashboardLayoutService::defaultLayout(): array`
- `DashboardLayoutService::getForCampaign(string $campaignCode): array`
- `DashboardLayoutService::saveForCampaign(string $campaignCode, array $sectionOrder, array $visibleSections): array`
- Normalized layout shape: `['sections' => ['welcome' => ['visible' => true, 'order' => 10], ...]]`.

- [ ] Write service tests for defaults, valid reorder/hide, unknown/duplicate section normalization, and campaign isolation; write feature tests for admin-only access and dashboard rendering.
- [ ] Run the new tests and confirm they fail before implementation.
- [ ] Create the SQLite-compatible migration/model and service with a fixed section catalog: `welcome`, `kpis`, `activity`, `leaderboard`, `campaign_report`, `forms`, `quick_links`; normalize every saved payload to that catalog.
- [ ] Add the admin update request with array validation and role authorization, the controller action using the resolved campaign, and the route under an `Admin,Super Admin` role middleware group.
- [ ] Render the editor with visibility checkboxes and up/down controls that submit ordered section keys; show the current campaign and a success flash after applying.
- [ ] Pass the normalized layout to `dashboard.blade.php`, wrap the existing sections in order-aware/visibility-aware containers, and preserve current data/modal/chart markup.
- [ ] Run the focused service/feature tests and Pint.

### Task 4: Dashboard live publication event

**Files:**
- Create: `app/Events/DashboardLayoutUpdated.php`
- Modify: `app/Http/Controllers/Admin/AdminDashboardController.php` (dispatch after save)
- Modify: `resources/js/echo.js` (listen to layout event)
- Modify: `tests/Unit/Events/DashboardLayoutUpdatedTest.php`
- Modify: `tests/Feature/Admin/AdminDashboardLayoutTest.php`
- Modify: `openspec/changes/data-master-vicidial-workspace-dashboard/specs/dashboard-live-updates/spec.md` only if verification identifies wording drift

**Interfaces:**
- Broadcast name: `dashboard.layout.updated`
- Channel: private `dashboard.{campaign}`
- Payload: `campaign`, `action`, `updated_at` only.

- [ ] Add event tests for channel, broadcast name, payload, and no event on failed authorization/validation.
- [ ] Run the new event test and confirm it fails before implementation.
- [ ] Implement the `ShouldBroadcastNow` event and dispatch it only after successful normalized layout persistence.
- [ ] Extend `subscribeDashboardChannel` to listen for both `.dashboard.data.updated` and `.dashboard.layout.updated`, with one teardown removing both listeners.
- [ ] Run event/dashboard tests and confirm the existing dashboard debounce refresh remains the single refresh path.

### Task 5: Verification and OpenSpec alignment

**Files:**
- Modify: `openspec/changes/data-master-vicidial-workspace-dashboard/tasks.md` check completed tasks
- Modify: main specs through OpenSpec sync after verification

- [ ] Run `php artisan test --compact` for the affected feature/unit files, `node --test tests/JavaScript/widgets/*.test.js`, `vendor/bin/pint --dirty --format agent`, and `npm run build`.
- [ ] Start or use the local Laravel app and use Playwright to select a different Data Master form while an active phone widget is present; verify the phone iframe element remains mounted and inspect console/network errors.
- [ ] Use Playwright to search Data Master records, move to another pagination page, toggle split view at desktop width, resize to a narrow viewport, and confirm the floating fallback.
- [ ] Use Playwright or authenticated feature coverage to apply a dashboard layout as Admin and verify the user dashboard reflects it after the broadcast/soft refresh path.
- [ ] Run `openspec verify change data-master-vicidial-workspace-dashboard`, update task checkboxes with actual results, and run the OpenSpec sync step.
- [ ] Review `git diff --check`, `git status`, and the final diff before reporting exact verification evidence.

## Why

The CRM's telephony workflows are capable, but the Agent Screen presents advanced call tools as one long control wall, role-scoped navigation exposes too many sibling destinations, and no-data states are not expressed consistently across operational screens. This increases scan time during live calls and makes valid empty, unavailable, and unconfigured states feel like failures.

## What Changes

- Add a task-focused Agent Screen control navigator that keeps call status and lead context visible while progressively disclosing advanced telephony tools.
- Make the transfer shortcut open the transfer tool directly instead of only scrolling the page.
- Make sidebar sections collapsible, preserve all existing route and authorization visibility, and automatically reveal the section containing the active route.
- Add a reusable non-table empty-state component with clear title, explanation, state tone, and optional next action.
- Apply the shared empty-state language to the Agent Screen, dashboard activity/submission surfaces, and major reporting sections without changing report data contracts.
- Retain the existing dark theme and token system while reducing unnecessary primary-accent emphasis in the new interaction surfaces.
- Add server-rendered regression tests for the new navigation, disclosure, and empty-state contracts.

## Capabilities

### New Capabilities

- `agent-workspace-focus`: Task-focused progressive disclosure for advanced Agent Screen controls while preserving telephony behavior.
- `crm-empty-state-guidance`: Consistent user-facing states for empty, unavailable, and configuration-required content.

### Modified Capabilities

- `responsive-crm-shell`: Collapsible, route-aware sidebar sections with accessible expanded state while preserving responsive behavior.

## Impact

- Affected views: `resources/views/agent`, `resources/views/layouts/sidebar.blade.php`, `resources/views/dashboard.blade.php`, `resources/views/reports/index.blade.php`, and selected admin empty states.
- Affected styling: `resources/css/app.css` for sidebar section controls, Agent Screen tool navigation, and shared empty-state presentation.
- Affected client behavior: the Agent Screen Alpine component and existing soft-navigation/sidebar lifecycle; no new dependency or backend endpoint.
- Affected tests: Blade/feature view rendering assertions and existing Agent Screen/report lifecycle coverage.
- No route, permission, telephony API, database, or dependency changes are intended.

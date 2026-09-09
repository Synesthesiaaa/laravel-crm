## Context

The application is a Laravel 12 Blade/Alpine CRM with a persistent responsive shell and telephony feature gates. The Agent Screen already owns the relevant call state and tool actions, while the sidebar and report/dashboard views are server-rendered with client-side refresh and chart lifecycle code. The change must improve task focus without changing routes, permissions, telephony endpoints, data contracts, or introducing a dependency.

## Goals / Non-Goals

**Goals:**

- Make the Agent Screen's primary call and lead context visually dominant.
- Expose one advanced telephony tool panel at a time through an accessible keyboard-operable navigator.
- Make the transfer shortcut open the transfer panel directly.
- Reduce persistent navigation density with collapsible, route-aware sections while preserving all existing links and role gates.
- Establish consistent non-table empty-state language and smaller chart fallbacks for empty, unavailable, and configuration-required states.
- Preserve the dark design tokens and use the existing magenta accent only for active/primary emphasis.

**Non-Goals:**

- No database, route, authorization, telephony API, or reporting contract changes.
- No change to the set of features exposed by telephony configuration flags.
- No full redesign of the dashboard information architecture, financial forms, or report data model in this pass.
- No new frontend dependency or replacement of the existing Alpine/Blade approach.

## Decisions

### Use local Alpine disclosure state for Agent tools

The Agent Screen will add an `activeTool` property and render tool buttons as a tablist with one `tabpanel` visible at a time. Alpine `x-show` is appropriate for local state and keeps existing included partials mounted, so input state and event handlers are not destroyed when users change tools. `x-cloak` prevents hidden panels from flashing before Alpine initializes.

The default state is a compact prompt rather than an arbitrary advanced tool. Existing feature gates determine which tabs render. The transfer keyboard shortcut sets `activeTool` to `transfer` before scrolling to the navigator.

Alternative considered: separate routes or modal dialogs for every tool. Rejected because it would add navigation/context loss and risk to telephony workflows.

### Keep navigation links but collapse secondary groups

The sidebar will compute active groups server-side using its existing route-matching closure, then use local Alpine state for expanded/collapsed sections. The section containing the current route opens automatically; other role-scoped groups can be collapsed by the user. Mobile drawer behavior and the existing persistent sidebar store remain unchanged.

Alternative considered: remove low-frequency links. Rejected because route visibility and role access are existing product contracts and the first pass should be reversible.

### Add a reusable empty-state component and preserve table semantics

`x-empty-state` will cover non-table content with an icon, title, explanation, tone, and optional action. Existing `x-table.empty` remains responsible for table row structure. Dashboard chart containers will render a compact server-known fallback when values are empty and a useful unavailable fallback if the chart loader cannot render data. Report JavaScript will use the same state vocabulary for dynamic sections.

Alternative considered: one generic message string everywhere. Rejected because users need to distinguish no records, missing configuration, unavailable sources, and loading failures.

### Use tokens and existing component conventions

New CSS will consume existing `--color-*`, radius, motion, and spacing variables. No new palette will be introduced. Interactive states will keep visible focus styling and a minimum 44px target where a control is actionable.

### Validate at the view and browser layers

PHPUnit feature tests will assert the rendered disclosure/navigation/empty-state contracts without coupling to telephony API responses. JavaScript/build checks will cover syntax and assets. Playwright will verify the Agent Screen and sidebar at representative desktop and mobile widths when the browser surface is available.

## Risks / Trade-offs

- [Risk] Advanced tools are less immediately visible to experienced agents. → Keep the navigator persistent, use clear labels, open the target tool from the transfer shortcut, and preserve direct action functions.
- [Risk] Collapsed navigation can hide a destination. → Auto-open the active group, expose `aria-expanded`, retain visible labels when expanded, and keep the mobile close/destination behavior unchanged.
- [Risk] Empty fallbacks could mask a chart rendering failure. → Distinguish server-known empty data from chart-unavailable states and retain report refresh/source-health messaging.
- [Risk] Dynamic tab content may be announced noisily by assistive technology. → Use explicit tab/tabpanel relationships, avoid unnecessary live regions, and verify focus order with keyboard testing.

## Migration Plan

No migration is required. Deploy the Blade/CSS/JS changes with the existing asset build. Rollback is a file-level revert of the affected view/component/style changes; no persisted data is altered.

## Open Questions

None for this implementation pass.

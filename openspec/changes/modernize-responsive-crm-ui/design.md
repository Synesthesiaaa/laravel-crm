## Context

The application is a Laravel 12 Blade application using Tailwind CSS 4, Alpine.js 3, Vite, and a small soft-navigation layer. The persistent sidebar, header, phone widget, and global overlays remain mounted while `#main-layout` is swapped. Theme selection is stored in `data-theme` on the document, while the Activity Log page uses an Alpine component with Echo realtime delivery and a five-second polling fallback.

The current shared system is dark-first with a saturated magenta primary color. The desktop shell is usable, but the sidebar is a long flat list, the header has several compact icon controls, and the Activity Log uses a desktop terminal grid that becomes dense on small screens. Existing soft-navigation and telephony state must remain stable.

## Goals / Non-Goals

**Goals:**

- Create a coherent semantic token layer that works in both themes and maintains accessible contrast.
- Make the shared shell predictable at 375px, 768px, 1024px, and 1440px widths.
- Preserve route visibility rules, active navigation state, theme persistence, soft navigation, realtime/polling behavior, and telephony widgets.
- Give keyboard and touch users visible focus, sufficient hit areas, clear status text, and no hidden primary actions.
- Make Activity Log filters, controls, rows, and expanded details readable without horizontal scrolling on phone widths.

**Non-Goals:**

- Changing business logic, routes, authorization rules, activity payloads, or telephony behavior.
- Redesigning every page-specific layout in this milestone.
- Adding a UI framework, icon dependency, or another frontend package.
- Removing the terminal character of the Activity Log; it remains a purposeful audit-console surface.

## Decisions

### 1. Extend the existing token system instead of introducing a second design system

Update `resources/css/app.css` semantic variables and shared component classes. Keep `var(--color-*)` references as the contract used by Blade views, status components, charts, and widgets. Use a navy/slate surface family with an accessible blue primary accent and retain separate success, warning, danger, and info tokens. Keep light/dark overrides side by side so each state is designed as a pair.

Alternatives considered: a page-local redesign would create visual drift; a new component library would add dependency and migration risk without improving existing soft-navigation behavior.

### 2. Keep the persistent sidebar pattern, but improve hierarchy and responsive behavior

Retain the fixed desktop sidebar and off-canvas mobile drawer because telephony widgets and soft navigation depend on persistent chrome. Improve section labels, active indicators, focus treatment, collapsed tooltips, mobile close behavior, and spacing. Keep all current route and role checks in Blade. The main layout will continue to calculate its desktop offset from the sidebar tokens and use a full-width mobile layout.

Alternatives considered: replacing the sidebar with bottom navigation would not fit the number of CRM/admin destinations and would conflict with desktop-first operational workflows.

### 3. Use CSS and Alpine semantics already present for interaction improvements

Use native buttons, links, labels, `aria-expanded`, `aria-pressed`, `aria-live`, and `:focus-visible` rather than custom event systems. Shared transitions will use existing CSS and Alpine bindings, with a global reduced-motion rule. Any page-level script remains in `@push('scripts')` and must clean up timers/subscriptions through Alpine `destroy()` so soft navigation remains safe.

Alternatives considered: adding a second JavaScript navigation/controller layer would duplicate existing Alpine stores and increase lifecycle bugs.

### 4. Preserve Activity Log behavior while changing its layout model

Keep `activityLogTerminal()` methods and the existing history endpoint unchanged. Adjust the Blade structure and page-local CSS so desktop rows use aligned columns while phone rows use stacked metadata and description, with expanded details as a readable block. Keep filters labeled and wrap them into a single-column mobile form. Keep connection status, follow, pause, clear, and live region semantics visible on all widths.

Alternatives considered: replacing the terminal with a data table would lose the existing audit-console affordances; a pure card list at every width would reduce scan efficiency for desktop operators.

### 5. Validate the persistent-shell lifecycle, not only first-load markup

Automated coverage will assert important rendered hooks and Activity Log behavior without requiring browser-only visual snapshots. Playwright will validate desktop/mobile layout, sidebar open/close, theme toggle, soft-navigation return, Activity Log filter controls, and console/network health.

## Risks / Trade-offs

- **[Token changes affect many pages]** → Keep variable names stable, use semantic mappings, build the frontend, and inspect representative dashboard, admin, form, and report routes.
- **[Soft navigation can duplicate page scripts]** → Do not move page scripts into persistent chrome; preserve Alpine `destroy()` cleanup and test leaving/returning to Activity Log.
- **[Terminal content is inherently wide]** → Allow only bounded `overflow-wrap`/code scrolling for long JSON values; prevent the main page from gaining horizontal overflow.
- **[Dark/light contrast regressions]** → Test both themes in Playwright and keep status colors paired with text labels, not color alone.
- **[Mobile controls may wrap unexpectedly]** → Use 44px minimum interactive bounds, explicit flex wrapping, and representative 375px and 768px checks.

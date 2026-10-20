## Context

Laravel 12, Blade, Alpine, Tailwind 4, and Vite supply the existing UI. Agents and administrators share navigation and semantic controls. Branding and campaign/role boundaries must survive the redesign. Frontend design uses the locally installed taste-skills only, specifically minimalist-ui; its guidance is adapted to the existing application and working components.

## Goals / Non-Goals

Goals: neutral, readable surfaces; a compact 248px sidebar; clear campaign context; visible global search; consistent form/table rhythm; lower dashboard visual noise; accessible responsive behavior.

Non-goals: replacing Blade/Alpine, changing telephony, modifying permissions or queries, adding libraries, changing business data, or rebuilding public marketing pages.

## Decisions

- Keep the installed framework and icon components to preserve behavior and avoid a cosmetic framework migration. Use restrained system sans typography, sentence-case labels, flat surfaces, 8px controls and 12px panels.
- Scope the redesign stylesheet to an explicit interface class. Preserve existing semantic colors for statuses and configured branding; use charcoal primary controls and one restrained rose accent for wayfinding.
- Default to light only when no valid saved preference exists. Dark mode remains supported. The header search trigger works on mobile and has a desktop label and keyboard hint.
- Preserve existing route-based navigation, disclosures, and feature gates. Display the current campaign in the sidebar footer. Override desktop collapse presentation on mobile, keeping all labels and sections usable.
- Flatten welcome and summary components while retaining dynamic statistics, dialogs, layout settings, and live refresh behavior.
- Repair the existing search interaction: watch the debounced query, discard stale responses, focus actual result links with arrow keys, refocus on reopen, and distinguish request errors from empty results. Linked statistics render native anchors for keyboard navigation.
- Restrict persisted collapse rules to desktop and keep mobile section toggles independent of that saved preference. A CSS override alone cannot reliably fix the original important display rule, so its breakpoint is corrected at the source.

## Risks / Trade-offs

- Shared styling has broad reach -> scope styles and verify representative dashboard, reports, records, attendance, administration, forms, and sign-in views.
- Mobile and persisted desktop collapse can conflict -> explicitly restore full mobile labels and test the transition.
- Existing backend integrations can be unavailable locally -> use isolated synthetic database/browser fixtures, report external limitations accurately.

## Migration Plan

Build Vite assets and deploy source normally. No migrations. Roll back source and rebuild assets to restore prior presentation.

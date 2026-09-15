## Context

The application is a Laravel 12 Blade/Alpine CRM with a documented Impeccable design system. The current visual language is dark, operational, and centered on Signal Magenta for primary emphasis. Recent work already improved Agent tool disclosure, navigation grouping, empty states, notifications, Data Master workspace behavior, and CRM form persistence/review.

## Goals

- Make shared controls accessible and predictable by default.
- Reduce operational scan cost without hiding common actions.
- Improve responsive behavior for dense tabs, filters, and dynamic tables.
- Reuse existing design tokens and components instead of introducing a new visual language.
- Keep behavior changes reversible and isolated from telephony and business logic.

## Decisions

### Shared primitives first

Form help text will be programmatically associated with its control, ordinary tables will keep native table semantics, actionable controls will meet the documented 44px target, static cards will stop implying clickability, and responsive tab/filter patterns will be reusable across operational screens.

### Progressive disclosure for dense filters

Reports and Call History will keep the date/scope controls used most often visible while moving secondary scope filters behind an accessible `More filters` disclosure. Existing filter values and refresh behavior remain unchanged.

### Preserve domain behavior

Agent dial/hangup controls gain explicit accessible names and dynamic capture fields gain stable label/control relationships. Supervisor tab semantics and notification fields are corrected without changing polling, routing, or action APIs. Data Master preserves its sticky header while allowing horizontal scrolling for wide dynamic schemas.

### Browser validation

The implementation will be checked at 375px, 768px, 1024px, and 1440px with keyboard navigation, overflow checks, console health, and representative operational interactions.

## Non-Goals

- No visual rebrand or palette replacement.
- No migration to React or a new component framework.
- No route, database, permission, telephony API, report-contract, or sales-calculation changes.
- No duplication of completed Agent disclosure, empty-state, notification, dashboard-layout, autosave, or review-confirmation work.

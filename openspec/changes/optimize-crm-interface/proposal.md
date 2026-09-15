## Why

The CRM already has a coherent Signal Magenta design system and strong domain-specific workflows, but shared interaction patterns are still implemented inconsistently across Agent, Reports, Supervisor, Records, Data Master, and administration screens. These inconsistencies increase scan time, produce avoidable accessibility gaps, and make dense operational pages harder to use on smaller screens.

## What Changes

- Harden shared form, table, button, card, navigation, and responsive-tab primitives so accessibility and interaction behavior are consistent by default.
- Make the mobile sidebar non-interactive while closed and keep collapsed desktop navigation usable by keyboard.
- Reduce filter density in Reports and Call History through progressive disclosure while keeping primary filters immediately available.
- Normalize Agent, Supervisor, Records, and Data Master markup around the existing design system and responsive patterns.
- Preserve the current Signal Magenta palette, Blade/Alpine architecture, routes, authorization, campaign scoping, telephony contracts, and business logic.
- Avoid duplicating already-completed work for Agent tool disclosure, shared empty states, notification behavior, dashboard layout controls, and CRM form autosave/review.

## Impact

- Shared UI: `resources/views/components`, `resources/views/layouts`, and `resources/css/app.css`.
- Operational views: Agent Screen, Reports, Supervisor, Records/Call History, and Data Master.
- Client shell behavior: sidebar focus/visibility behavior in `resources/js/app.js`.
- Tests: focused server-rendered view assertions plus existing JavaScript/build/browser validation.
- No database, route, API, telephony, or dependency changes are intended.

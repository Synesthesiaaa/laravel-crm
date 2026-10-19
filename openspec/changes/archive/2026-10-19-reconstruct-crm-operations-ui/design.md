## Context

Laravel 12.53, Blade, Alpine 3.15 and Tailwind 4.2 already have a flat dual-theme shell in `crm-minimal.css`. Four actual roles exist: Agent, Team Leader, Admin and Super Admin; QA and Operations Manager are responsibilities rather than additional roles. Backend role middleware remains authoritative.

## Goals / Non-Goals

Goals: consistent task hierarchy, usable dense data, discoverable operations, accessible disclosure, retained state, synthetic evidence for every accessible page family.

Non-goals: framework replacement, feature deletion, KPI recalculation, schema changes, changes to external telephony/email transport, production deployment.

## Decisions

1. Evolve the incumbent system. Taste current app run deals Framed sheet, seed 325312072, with palette/type retained. Neutral chrome surrounds a flat content sheet; magenta marks the selected operation and meaningful data. Existing platform font remains. Controls keep 44px targets despite smaller study medians.
2. Organize navigation through metadata on existing `AdminNavigation` destinations. Overview, Operations, Analytics, Management and Administration distinguish daily work from configuration. Never manufacture a role or route. Keep dynamic campaign forms and Agent Screen feature gate.
3. Use a reusable native details/summary creation panel. Tables remain first-class; opening/closing a panel does not unmount fields. Validation and old input reopen the correct panel, with an explicit exception for row-edit errors. No modal proliferation or new JS lifecycle.
4. Use GET links for email sub-workspaces. Prepare, templates and delivery list have stable URLs and ordinary form posts. Recipient-specific documents, preflight, password retention, synthetic test bag and queued/provider status remain unchanged. Workflow steps are presentation only and never authorize sending.
5. Shared tables get labelled keyboard-scroll regions; forms use semantic groups and named error/help relationships. All screens reuse semantic color tokens, bounded widths, flat panels and visible focus. Agent layout is presentation-only: preserve x-data, event handlers, persistent phone and quick-form roots.
6. A synthetic SQLite fixture and local server exercise routes without altering `.env` or operational data. External WebRTC/SMTP/VICIdial live behavior remains explicitly unverified if unavailable. Capture before/after, themes, mobile/desktop, errors and interaction results.
7. Default launcher geometry uses the 48px launcher rather than the expanded form width: a bounded corner stack with an 8px gap and 160px of bottom scrolling space. Narrow quick-form panels reserve the corner margin. Existing saved drag layouts, split mode and call lifecycle remain intact.

## Verification result

745 PHP tests / 4,039 assertions and 47 JavaScript tests pass. Vite builds. Browser evidence covers 102 primary screen/viewport checks, eight standalone checks, three additional role sessions and eight final launcher recaptures. Independent finish review cleared its three material fixes. See `docs/ui-reconstruction-validation.md` for precise scope, retained warnings and external-service limits; this is local UI acceptance, not production integration certification.

## Risks / Trade-offs

Disclosure can hide a creation action; the summary must name it and sit immediately above its records. Navigation regrouping must retain every route and update active-group logic. Native details retains form state but has no animated transition, deliberately. Shared presentation cannot certify remote integration behavior. Route families with data-dependent edit/review pages need synthetic records. Avoid blanket CSS selectors that change semantic state colors or widget internals.

## Rollout

Audit and baseline first; shared foundations and navigation next; management and email workflows next; agent/report/settings refinements next; automated and browser parity checks last. Keep the OpenSpec change active until all required verification is complete. Rollback is the focused source diff; no schema rollback is needed.

## Why

The authenticated `/dashboard` currently carries excessive first-load JavaScript and late-rendered UI work, while its floating telephony widget and asynchronously mounted visualizations can move the page after first paint. The supplied Lighthouse report indicates materially high blocking time and layout shift, so the dashboard needs a measured, production-build remediation that improves responsiveness and accessibility without weakening CRM security, real-time behavior, or business functionality.

## What Changes

- Establish a clean, extension-free production Lighthouse baseline and retain the supplied contaminated report as historical context.
- Trace and reduce dashboard-only JavaScript work through route/feature-level loading, deferred chart/form initialization, polling/listener cleanup, and removal of proven duplicate or unused execution.
- Lazy-load below-the-fold or interaction-triggered ApexCharts features while reserving stable chart dimensions and preserving accessible text/table fallbacks.
- Make the floating phone/telephony widget layout-stable and ensure asynchronous dashboard content does not shift surrounding content.
- Profile and improve the dashboard request path, query behavior, and short-lived aggregate caching only where measurements show safe benefit; keep real-time data and authenticated cache semantics correct.
- Preserve long-lived caching for hashed static assets, optimize font/CSS delivery where supported by the existing build, and verify production compression/cache headers without changing dependency versions.
- Correct shared dashboard/shell contrast tokens, sidebar semantics, authenticated-page indexing policy, and keyboard/focus behavior identified by the audit.
- Add regression coverage for changed server behavior and browser validation for dashboard loading, widgets, charts, responsive layout, accessibility, and navigation.
- Record actual clean pre-fix and post-fix Lighthouse/build measurements; do not invent results or claim targets that were not measured.

## Capabilities

### New Capabilities

- `dashboard-performance-runtime`: Defines dashboard-specific asset loading, deferred visualization/widget behavior, layout stability, measurement, and performance-safety contracts.

### Modified Capabilities

- `production-ui-runtime`: Extend the production runtime contract for dashboard-scoped asset delivery and immutable hashed-asset behavior where implementation changes the observable asset boundary.
- `responsive-crm-shell`: Clarify stable floating telephony-widget layout, semantic sidebar structure, contrast, and keyboard-safe behavior where the shell is changed.
- `dashboard-summary-comparison`: Preserve the existing accessible summary/chart contract while documenting any changed deferred chart mounting behavior.

## Impact

- Affected areas include the dashboard controller/services, shared Blade layout and dashboard views, Alpine/JavaScript entry points and widget modules, Tailwind/CSS tokens, Vite configuration, web/server asset delivery configuration, and focused PHPUnit/browser tests.
- No new runtime dependency or public API is required unless profiling proves an existing capability cannot be safely optimized in place.
- Authenticated dashboard responses remain non-indexable and non-publicly-cacheable where required; telephony/WebSocket behavior and campaign/permission scoping remain intact.

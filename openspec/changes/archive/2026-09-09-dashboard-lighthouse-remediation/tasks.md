## 1. Establish route-scoped asset loading

- [x] 1.1 Remove agent-capture-only behavior from the shared app dependency graph and keep its dedicated entry functional.
- [x] 1.2 Add a lean embedded-form Alpine entry and update the widget view to use it without Echo, telephony, notifications, or chart dependencies.
- [x] 1.3 Add a dashboard-specific chart/lifecycle entry with an idempotent soft-navigation initializer and preserve existing chart controls/live refresh behavior.

## 2. Defer dashboard work without changing data contracts

- [x] 2.1 Add an authenticated campaign-scoped activity endpoint that returns the existing daily, weekly, and monthly trend shapes in one response.
- [x] 2.2 Remove below-fold activity aggregation from the initial dashboard controller/view path and make chart cards render stable loading/error states.
- [x] 2.3 Lazy-load ApexCharts and activity data by viewport, preserve the summary chart table fallback, and ensure mode controls remain keyboard-operable.
- [x] 2.4 Deduplicate widget-layout reads while preserving per-widget hydration, debounced saves, and fallback defaults.

## 3. Stabilize and polish the shared shell

- [x] 3.1 Defer closed Quick Form iframe/API work until activation while loading it automatically for split view or an already active form page.
- [x] 3.2 Preserve fixed widget dimensions and add layout-stability safeguards for asynchronous hydration and chart mounting.
- [x] 3.3 Correct measured light-theme contrast and sidebar landmark semantics, and add authenticated noindex metadata without exposing sensitive content.

## 4. Regression coverage and verification

- [x] 4.1 Add/update PHPUnit coverage for the activity endpoint, route-scoped view/asset contracts, and cache/request behavior.
- [x] 4.2 Add/update JavaScript coverage for lazy chart lifecycle, shared layout reads, and Quick Form activation behavior where the existing test harness supports it.
- [x] 4.3 Run focused PHPUnit tests, Pint, the production Vite build, and existing JavaScript tests; fix regressions.
- [x] 4.4 Use Playwright to verify authenticated dashboard/form/widget/soft-navigation flows, console health, network behavior, accessibility landmarks, and 375/768/1024/1440 widths.
- [x] 4.5 Run clean extension-free Lighthouse against the production build, record actual before/after metrics and bundle/request evidence, sync the specs, and archive only after all required checks pass.

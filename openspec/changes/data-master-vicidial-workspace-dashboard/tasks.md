## 1. Data Master navigation and search

- [x] 1.1 Add focused PHPUnit coverage for marked GET Data Master navigation, search matching/no-match behavior, query-preserving pagination, and invalid search input.
- [x] 1.2 Extend Data Master service/controller/view flow with a bounded search term, safe schema-column filtering, clear-search UI, and query-preserving pagination.
- [x] 1.3 Extend soft navigation to intercept only marked GET forms and add the Data Master selector marker, preserving normal form fallback behavior.
- [x] 1.4 Run the focused Data Master and platform lifecycle tests.

## 2. Persisted Quick Form and Vicidial split workspace

- [x] 2.1 Add JavaScript tests for workspace preference normalization, split toggle events, and desktop/narrow geometry decisions.
- [x] 2.2 Allow the authenticated widget layout API to persist the `workspace` split-screen key with strict validation.
- [x] 2.3 Implement the shared workspace browser controller and wire both widget components to split/exit events and responsive geometry.
- [x] 2.4 Add split-view controls and responsive styles without changing Vicidial session or iframe contracts.
- [x] 2.5 Run the widget JavaScript tests and Vite build.

## 3. Admin-controlled campaign dashboard layout

- [x] 3.1 Add the campaign-scoped dashboard layout migration, model, service defaults, normalization, and persistence tests.
- [x] 3.2 Add admin-only layout update request/controller route coverage for authorization, validation, campaign scoping, persistence, and success feedback.
- [x] 3.3 Add the admin dashboard visibility/order editor and apply the normalized layout to the user dashboard sections.
- [x] 3.4 Add the campaign-scoped layout broadcast event and dashboard Echo refresh handling, with regression coverage for the update signal.
- [x] 3.5 Run the focused dashboard tests and format all changed PHP files with Pint.

## 4. End-to-end verification and spec alignment

- [x] 4.1 Run the affected PHPUnit files, JavaScript tests, and `npm run build` together and resolve failures.
- [x] 4.2 Use Playwright to verify Data Master form switching/search, split view at desktop/mobile sizes, and admin-to-user dashboard publication.
- [x] 4.3 Run the supported OpenSpec validation command and sync the completed delta specs.
- [x] 4.4 Review the final diff and report exact tests, browser checks, assumptions, and any unverified external Vicidial behavior.

## 5. Quick Form widget form selector

- [x] 5.1 Extend the authenticated Quick Form bootstrap response with active current-campaign form options and cover the response contract with PHPUnit.
- [x] 5.2 Add pure JavaScript normalization and selection guards for malformed or unavailable form options.
- [x] 5.3 Add the Quick Form selector UI and switch only the Quick Form iframe source while preserving the Vicidial iframe and split-workspace state.
- [x] 5.4 Verify the selector in normal and split views with focused tests, the Vite build, and browser interaction checks.

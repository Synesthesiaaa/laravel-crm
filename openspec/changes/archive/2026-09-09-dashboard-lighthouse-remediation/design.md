## Context

The supplied Lighthouse report is not a clean application baseline: it includes extension and injected-script activity, while its blocking-time result is inconsistent with the current first-party trace. A clean authenticated desktop run against the production Vite build measured Performance 54, Accessibility 92, Best Practices 96, and SEO 91. The same run measured FCP 2.1 s, LCP 2.8 s, TBT 60 ms, CLS 0.349, a 930 ms document response, 34 requests, and about 1.97 MiB transferred. First-party attribution shows an approximately 355 kB shared application bundle, a 476 kB ApexCharts chunk loaded during initial dashboard work, a 131 kB agent-capture entry reachable from the embedded form application, six widget-layout requests across the observed session, and three below-fold activity datasets computed by the dashboard controller.

The application is a Laravel 12 CRM with an Alpine/Vite shell, persistent telephony widgets, Reverb updates, soft navigation, campaign-scoped data, and server-rendered dashboard tables. The remediation must preserve authentication, authorization, telephony/WebRTC and VICIdial behavior, realtime refresh, form submission, campaign visibility, and the existing design language.

## Goals / Non-Goals

**Goals:**

- Make the dashboard initial document and JavaScript path smaller by separating route-only work from shared shell work.
- Defer ApexCharts and below-fold activity data until the relevant dashboard region is near the viewport, while keeping server-rendered summary data and a table fallback available.
- Prevent the closed Quick Form from creating a document and loading a second full CRM shell before the user requests it.
- Deduplicate the shared widget-layout request without changing persisted layout semantics.
- Reduce avoidable dashboard TTFB work by moving below-fold trend aggregation to one authenticated endpoint and reusing request-local campaign metadata.
- Remove measured accessibility and indexing defects, keep stable space for async charts/widgets, and preserve keyboard/focus behavior.
- Produce repeatable clean production-build measurements and browser regression evidence.

**Non-Goals:**

- No dependency upgrades, new third-party dependencies, database schema changes, or public API redesign.
- No removal of telephony, WebSocket, campaign, notification, form, or soft-navigation features.
- No fabricated Lighthouse targets or claims about extension-contaminated measurements.
- No broad cache policy change for authenticated HTML or user-specific API responses.
- No visual redesign outside the existing CRM tokens and component patterns.

## Decisions

### 1. Use route-specific Vite entries for the dashboard and embedded form

The shared `app.js` will no longer import the agent-capture controller because the agent screen already has its own entry. The embedded form will use a small Alpine/form entry that provides Axios, form visibility, and the focus plugin without Echo, notifications, telephony, charts, or persistent widgets. The dashboard will load a dedicated chart/lifecycle entry from its page script stack. The normal full form page continues to use the shared shell and app entry.

This choice keeps the existing Vite/Blade deployment model and hashed asset behavior. It avoids a runtime route detector inside the shared bundle and keeps soft-navigation compatibility through an explicit dashboard initializer.

Alternative considered: retain one universal app bundle and rely only on HTTP caching. Rejected because the clean trace attributes a large amount of parse/evaluation work to code that is not needed by the embedded form and because cache hits do not reduce first-visit main-thread work.

### 2. Lazy-load chart code and activity data by viewport

The dedicated dashboard entry will observe the server-rendered summary chart and activity chart region. It will load ApexCharts only when a chart is near the viewport or the user requests chart interaction. The summary chart keeps its fixed-height loading shell, live status, and daily table fallback. The three activity charts will be mounted together after one authenticated activity response, rather than issuing three client requests or computing those datasets in the initial HTML request.

The dashboard entry will expose an idempotent initializer so the existing soft-navigation system can rehydrate the dashboard when returning to it without depending on module re-evaluation.

Alternative considered: render all charts immediately after `load`. Rejected because it creates the measured ApexCharts request and multiple layout/resize passes during the initial critical path.

### 3. Keep widget chrome fixed and defer only optional embedded content

The phone widget remains available at boot because its VICIdial/WebRTC session lifecycle is persistent and business-critical. Its fixed shell dimensions and 1px minimized frame slot remain stable. The Quick Form retains its launcher and persisted layout, but it will resolve the default form and assign the iframe source only when opened (or when split view explicitly opens it). This removes hidden iframe document, font, and app work from the dashboard initial path without changing form selection or submission behavior after activation.

The layout persistence module will share an in-flight GET promise for `/api/widgets/layouts`; both widget consumers still hydrate their own keys and retain their existing debounced PUT behavior.

Alternative considered: hide the existing iframe with CSS while continuing to load it. Rejected because it preserves the duplicate document and bundle cost and does not address the measured root cause.

### 4. Move below-fold trend aggregation behind an authenticated dashboard endpoint

The dashboard controller will continue to render the summary, KPI/report tables, and all existing labels. It will stop calculating daily, weekly, and monthly chart datasets for the initial HTML response. A campaign-scoped authenticated JSON endpoint will return those three already-cached service results when the activity region is observed. The endpoint will use the current session campaign middleware and a single response shape.

Campaign repository metadata used repeatedly by dashboard aggregation will be memoized for the lifetime of the repository instance. Existing invalidation paths remain authoritative; no authenticated HTML cache is introduced.

Alternative considered: cache the entire dashboard HTML. Rejected because the page contains user- and campaign-scoped state, live controls, and security-sensitive content.

### 5. Correct the measured shared-shell accessibility defects with tokens

The light-theme muted/dim text tokens will be adjusted to meet readable contrast on their intended light surfaces, and the primary accent used for text links/statuses will have a readable light-theme value while retaining the brand treatment in dark mode. The sidebar will use the existing semantic `aside` plus labeled `nav` structure without assigning navigation semantics redundantly to the `aside`. A generic authenticated description and `noindex,nofollow` policy will be present for the private dashboard shell.

Alternative considered: suppress Lighthouse audits or remove text. Rejected because the defects affect real users and are not measurement noise.

## Risks / Trade-offs

- [Risk] A chart may not load until a user scrolls near it. → Keep a stable loading/status state, server-rendered summary table, and an explicit observer with a safe fallback when IntersectionObserver is unavailable.
- [Risk] A deferred activity request can fail or be throttled. → Keep empty/loading/error states in the reserved chart cards and preserve the rest of the dashboard; reuse existing Axios timeout and polling/backoff conventions.
- [Risk] Moving the Quick Form iframe source assignment could expose a soft-navigation edge case. → Initialize it on launcher activation, split-view activation, forms-page boot, and existing form-route synchronization; cover those paths in browser tests.
- [Risk] Module scripts are evaluated once even if soft navigation injects the same hashed URL again. → Make the dashboard entry's initializer idempotent and invoke it from the page boot plus the persistent `soft-navigate` event path.
- [Risk] Changing light-theme tokens can alter visual emphasis. → Keep semantic names and existing dark tokens, verify representative light/dark viewports, and check contrast and focus states in the browser.
- [Risk] Moving trend work to a later request changes when chart data is fetched, not its service calculation. → Use the same campaign-scoped service methods and add endpoint/response tests; do not change aggregation rules.

## Migration Plan

1. Add the dashboard chart and lean form entries, endpoint contract, and focused regression tests.
2. Build Vite assets and verify the hot-file/manifest behavior remains unchanged.
3. Deploy application code and the compiled manifest/assets together; hashed assets remain compatible with the existing static cache policy.
4. Run authenticated browser smoke checks for dashboard load, summary fallback/mode controls, Quick Form open/submit path, soft navigation, telephony widget visibility, and representative responsive widths.
5. Compare clean extension-free Lighthouse and network traces with the recorded clean pre-change baseline.
6. Roll back by restoring the previous application release and manifest/assets. No data migration or irreversible state change is introduced.

## Open Questions

- Production Lighthouse must be rerun against the deployment's real HTTPS, cache, compression, font, and database infrastructure; the local clean run is a diagnostic baseline, not a production claim.
- The current local environment uses an authenticated development server and has no representative production traffic volume, so backend query/index changes should only be added if later profiling identifies a measured bottleneck.

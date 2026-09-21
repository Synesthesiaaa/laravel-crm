## Why

The CRM's hover feedback, page transitions, and Alpine modal transitions work while developing with Vite, but are reported as inactive after deployment. These interactions depend on the production Vite manifest, compiled CSS utilities, and the Alpine bundle being served together; a stale or incomplete production asset set can leave pages visually static and interactive overlays hidden.

The production path needs an explicit, testable asset contract so a deployment cannot silently serve a mismatched or missing CSS/JavaScript build.

## What Changes

- Verify and harden production asset delivery for the shared Vite CSS and JavaScript entry points.
- Add a lightweight runtime health marker for the compiled UI bundle so the page can detect when Alpine and the expected interaction layer have not initialized.
- Preserve the existing hover, reduced-motion, soft-navigation, and modal behavior while making initialization failures observable and recoverable.
- Add automated coverage for the production asset contract and Alpine interaction bootstrap.
- Document the required build/cache steps for production deployment within existing deployment guidance.

## Capabilities

### New Capabilities

- `production-ui-runtime`: Ensures the production CSS/JavaScript bundle is present, initialized, and able to power shared UI interactions.

### Modified Capabilities

<!-- No existing requirement-level capability is being modified; this is a deployment/runtime hardening capability. -->

## Impact

- Affected frontend entry points: `resources/css/app.css`, `resources/js/app.js`, `resources/js/soft-navigate.js`, and the shared Blade layout.
- Affected deployment behavior: `npm run build`, Vite manifest/build output, and cache invalidation for the HTML shell.
- Affected automated coverage: shared layout/view-render tests and frontend asset/build verification.
- No database schema, API contract, or third-party dependency changes are required.

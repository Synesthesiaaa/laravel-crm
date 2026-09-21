## Why

The current login page works, but its centered card treats the CRM like a generic sign-in form and gives the configured brand and campaign-aware workflow little context. A more deliberate entry surface can make the product feel trustworthy and professional while reducing visual ambiguity for agents and supervisors who begin work here every day.

## What Changes

- Recompose the guest login page as a brand-led, campaign-aware entry surface within the existing dark/light CRM design system.
- Give the configured company identity a clear desktop presence beside the form, with a compact mobile arrangement that keeps the form immediately usable.
- Strengthen the heading, supporting copy, field grouping, campaign selector, feedback regions, and submit action without changing the existing login fields, routes, validation, or session behavior.
- Refine login-specific surfaces, spacing, typography, focus states, theme treatment, and responsive rules using existing semantic tokens.
- Preserve configured logo/name/favicon rendering, native campaign selection, keyboard access, reduced-motion behavior, and no-horizontal-overflow guarantees.
- Add or update focused feature assertions and browser checks for the redesigned structure and supported interaction states.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `login-experience`: Replace the minimal centered composition with a professional brand-led responsive entry composition while preserving the credential and campaign-aware behavior.

## Impact

- Affected view: `resources/views/auth/login.blade.php`.
- Affected styling: login-specific rules in `resources/css/app.css` and the generated Vite asset.
- Affected tests: `tests/Feature/LoginTest.php` and any browser validation covering the guest login route.
- No route, controller, request, session, authorization, database, dependency, or authentication-contract changes are intended.

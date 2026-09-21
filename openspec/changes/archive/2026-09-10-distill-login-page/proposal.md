## Why

The redesigned login page now communicates the product context through several parallel elements: a large context headline, supporting description, scope badge, and a separate auth sheet. For a repeated operational entry task, that extra layer competes with the one action users need to complete. This distill pass keeps the identity and campaign choice while reducing the page to a single, faster-scanning sign-in flow.

## What Changes

- Collapse the desktop split composition into one centered login flow with the configured brand lockup above the auth sheet.
- Remove the redundant context headline, supporting paragraph, and campaign-scope badge from the guest entry surface.
- Shorten the supporting copy and primary action while preserving clear form labels and campaign context.
- Reduce login-only atmospheric and decorative styling while retaining theme support, focus states, feedback states, and reduced-motion behavior.
- Preserve CSRF, route names, request fields, old input, campaign values, validation/status regions, configured branding, and theme persistence.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `login-experience`: simplify the composition and content hierarchy without removing the credential or campaign-aware login flow.

## Impact

- Affected files: `resources/views/auth/login.blade.php`, the login-specific section of `resources/css/app.css`, and focused login feature assertions.
- No controller, request, route, model, database, dependency, or authentication contract changes.
- Requires the normal Vite build and browser validation at desktop, tablet, and mobile widths.

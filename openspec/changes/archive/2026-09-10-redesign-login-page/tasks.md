## 1. Login surface structure

- [x] 1.1 Add the minimal semantic layout hooks and main landmark to `resources/views/auth/login.blade.php` while preserving all existing route fields, Blade conditionals, branding, theme toggle, and inline theme persistence.
- [x] 1.2 Add/update a focused `LoginTest` assertion for the rendered guest login structure and preserve existing authentication regression coverage.

## 2. Login surface styling

- [x] 2.1 Replace the login-specific gradient/glow-heavy composition in `resources/css/app.css` with the palette-aligned desktop/mobile layout and quiet auth sheet treatment.
- [x] 2.2 Refine form fields, campaign selector, feedback regions, submit button, theme toggle, focus states, and reduced-motion behavior with existing tokens and minimum touch targets.
- [x] 2.3 Review the resulting diff for obsolete login-only selectors, accidental global style changes, and layout overflow risks.

## 3. Verification and closeout

- [x] 3.1 Run the focused PHPUnit login test, Pint for modified PHP, and the production Vite build.
- [x] 3.2 Validate the login page with Playwright at mobile, tablet, and desktop sizes in dark and light themes, including keyboard focus, campaign selection, error/status rendering, and browser console/network health.
- [x] 3.3 Run the Impeccable detector plus the rendered audit/critique/polish review against the login surface and address any real Priority 0/1 issues.
- [x] 3.4 Synchronize the OpenSpec capability requirements with the main specs and archive the completed change.

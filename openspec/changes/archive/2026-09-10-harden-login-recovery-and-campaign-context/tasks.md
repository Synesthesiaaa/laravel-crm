## 1. Login view semantics and recovery

- [x] 1.1 Update the login Blade form with field-level errors, a live sign-in status region, password visibility control, and supervisor/help-desk recovery guidance.
- [x] 1.2 Render exactly one campaign as read-only context while preserving the native selector for multiple campaigns and omitting campaign UI when none exist.
- [x] 1.3 Add progressive-enhancement submit behavior that sets `aria-busy`, disables duplicate submits, announces “Signing in…”, and keeps the normal POST flow intact.

## 2. Visual and responsive refinement

- [x] 2.1 Add token-based styles for error copy, password toggle, read-only campaign context, help guidance, and the in-flight button state.
- [x] 2.2 Refine compact-height mobile spacing and reduced-motion behavior without reducing control touch targets.

## 3. Verification and specification

- [x] 3.1 Extend the login feature tests for single-campaign context, recovery guidance, password visibility, and rendered progress semantics.
- [x] 3.2 Run focused PHPUnit/Pint/build checks and validate the login flow at representative desktop and mobile viewports with Playwright.
- [x] 3.3 Sync the login-experience delta into the main OpenSpec and archive the completed change.

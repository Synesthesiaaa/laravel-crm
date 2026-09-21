## 1. Distill the login composition

- [x] 1.1 Collapse the login view to a compact configured-brand lockup above one auth sheet while preserving route, CSRF, request fields, old input, feedback, campaign data, and theme behavior.
- [x] 1.2 Remove redundant context copy, scope decoration, and non-essential submit decoration while retaining clear labels, grouping, and accessible semantics.

## 2. Simplify the visual system

- [x] 2.1 Replace the split login-only layout with a bounded linear flow using the existing CRM tokens and auth sheet treatment.
- [x] 2.2 Reduce atmospheric/depth decoration and keep a restrained, readable dark/light surface with no page-level overflow.
- [x] 2.3 Preserve 44px controls, visible keyboard focus, contrast-safe action states, validation/status presentation, selection/caret styling, and reduced-motion behavior.

## 3. Automated verification

- [x] 3.1 Update focused login feature assertions for the distilled structure while retaining credential, campaign, branding, theme, and validation coverage.
- [x] 3.2 Run the focused PHPUnit test, Laravel Pint for modified PHP files, and the configured Vite build.

## 4. Browser and OpenSpec closeout

- [x] 4.1 Run Playwright checks at representative 375px, 768px, 1024px, and 1440px widths for the linear layout, theme toggle, keyboard focus, form controls, and no horizontal overflow.
- [x] 4.2 Inspect dark/light and feedback screenshots for hierarchy, clipping, contrast, and reduced complexity; fix material defects in one bounded pass.
- [x] 4.3 Synchronize the modified login-experience requirement to the canonical OpenSpec spec and run final OpenSpec validation.
- [x] 4.4 Archive the completed OpenSpec change only after implementation and required validation are complete.

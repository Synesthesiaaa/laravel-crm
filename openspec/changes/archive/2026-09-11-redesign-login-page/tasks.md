## 1. Login composition

- [x] 1.1 Recompose `resources/views/auth/login.blade.php` into a brand/context rail plus dominant auth sheet while preserving configured branding, CSRF, route, field names, old input, feedback regions, and theme toggle behavior.
- [x] 1.2 Improve login copy and structural hooks so the workspace/campaign context, credentials, optional campaign selector, and submit action are clear to visual and assistive users without adding unsupported claims.

## 2. Visual system and responsive behavior

- [x] 2.1 Replace the login-specific CSS layout with the signal-desk entry composition using existing CRM tokens, deliberate spacing, readable type scale, restrained atmosphere, and explicit interactive states.
- [x] 2.2 Define desktop, tablet, and narrow-mobile layout rules with safe gutters, wrapped configured branding, 44px controls, and no page-level horizontal overflow.
- [x] 2.3 Preserve and refine light-theme bindings, visible keyboard focus, validation/status presentation, selection/caret styling, and reduced-motion behavior.

## 3. Automated verification

- [x] 3.1 Update focused login feature assertions for the redesigned structure while retaining coverage for the existing credential/campaign contract, branding, and validation feedback.
- [x] 3.2 Run the focused PHPUnit tests, Laravel Pint for modified PHP files, and the configured Vite build.

## 4. Browser and OpenSpec closeout

- [x] 4.1 Run Playwright checks at representative 375px, 768px, 1024px, and 1440px widths for layout, theme toggle, keyboard focus, form controls, and no horizontal overflow.
- [x] 4.2 Check browser console/network health and inspect desktop/mobile screenshots for contrast, clipping, hierarchy, and state coverage; fix any material defects found in one bounded pass.
- [x] 4.3 Synchronize the modified login-experience requirement to the canonical OpenSpec spec and run final OpenSpec validation.
- [x] 4.4 Archive the completed OpenSpec change only after implementation and required validation are complete.

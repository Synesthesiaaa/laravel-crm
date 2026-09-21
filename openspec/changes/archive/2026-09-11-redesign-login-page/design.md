## Context

The Laravel CRM guest login route is a server-rendered Blade view backed by `LoginController` and `LoginRequest`. It already supports configured branding, optional campaign selection, session status/errors, CSRF protection, theme persistence, and rate-limited credential submission. The shared CSS defines the CRM's dark-first token system and an auth-specific glass-sheet treatment, but the current login composition is effectively a narrow centered column and does not establish the product context before the user begins the form.

The redesign is an extension of the established CRM visual world, not a new brand system. The target is a professional “signal desk entry”: a quiet atmospheric canvas, a brand/context rail on larger screens, and a dominant auth sheet with clear task hierarchy. The layout must collapse without friction for narrow viewports and must keep all existing request names, routes, validation behavior, and branding inputs intact.

## Goals / Non-Goals

**Goals:**

- Give the configured company identity and campaign-aware workspace a purposeful first-viewport presence.
- Make the credential form the most prominent and fastest-scanned element.
- Improve hierarchy through stronger heading copy, a short context statement, deliberate grouping, and clearer feedback treatment.
- Use the existing semantic CSS tokens, DM Sans typography, contrast-safe token-derived magenta action variants, auth radius, and theme bindings.
- Preserve keyboard order, visible focus rings, reduced-motion behavior, native campaign selection, and mobile overflow safety.
- Keep the implementation Blade/CSS-only unless the existing page script needs a minimal accessibility-safe adjustment.

**Non-Goals:**

- No changes to authentication logic, route names, request fields, validation rules, session handling, campaigns, controllers, or authorization.
- No new frontend dependency, icon package, remote image, database change, or branding setting.
- No login registration, password reset, remember-me, SSO, or other new authentication feature.
- No redesign of the pending-login page or authenticated application shell in this change.

## Decisions

### Use a responsive split entry composition

At desktop widths, `.login-layout` will use a two-column grid with a left brand/context rail and a right form sheet. The context rail will carry the configured `x-brand`, a short product-facing heading, and a small campaign-scope note. At tablet and mobile widths it will become a compact block above the form, keeping the form inside safe gutters and within the first viewport whenever the content permits.

Alternative considered: keep a single centered card and only adjust colors and spacing. Rejected because it preserves the generic sign-in silhouette the user asked to move beyond and leaves the configured brand without a useful role.

### Keep the auth sheet as the visual anchor

The form remains in the existing auth-specific sheet with the shared charcoal/light-theme surface tokens, restrained atmospheric radial treatment, generous internal spacing, and the existing magenta submit hierarchy. The split rail adds context but does not compete with the credential task or introduce a second card system.

Alternative considered: replace the sheet with a full-bleed illustration or marketing panel. Rejected because the CRM has no supplied asset or marketing proof, and decoration would compete with repeated operational sign-in.

### Improve copy without inventing claims

The primary heading will identify the action and destination (for example, “Sign in to your workspace”), while supporting copy will explain the existing campaign-aware flow in plain language. The context note will describe campaign-scoped access rather than claim security guarantees, performance outcomes, or customer proof not present in the product evidence.

Alternative considered: add testimonials, metrics, or security badges. Rejected because those claims are not confirmed product content and are unnecessary for an operational entry surface.

### Preserve semantic controls and state contracts

The username and password controls retain their names, autocomplete values, floating labels, and validation attributes. The campaign remains a native `<select>` with the existing values and old-input behavior. Status/error regions keep `role="status"`/`role="alert"`, stable IDs, and readable content. New structural hooks use descriptive classes and data attributes so feature tests and browser checks do not depend on visual styling.

Alternative considered: introduce a custom campaign combobox or client-side form flow. Rejected because it adds interaction and accessibility risk without improving the core login task.

### Keep motion authored and bounded

The existing auth-sheet and theme-toggle entry treatment will be refined as one coordinated login moment. Reduced-motion media rules will make the sheet and theme control immediately visible and remove transitions/animation while retaining focus and state feedback. No content will depend on animation timing.

## Risks / Trade-offs

- [Risk] The additional context rail could push the form below the fold on short desktop windows. → Use a bounded grid, compact rail copy, responsive vertical spacing, and keep the form sheet independently readable.
- [Risk] Very long configured brand names could widen the desktop rail or mobile layout. → Apply `min-width: 0`, wrapping, `overflow-wrap: anywhere`, and bounded text measure to the brand/context content.
- [Risk] Low-opacity atmospheric effects could reduce contrast in light mode. → Build the atmosphere from existing token mixes, keep text on opaque/tonal surfaces, and verify both themes in the browser.
- [Risk] The existing view tests assert current class strings. → Update only presentation assertions to stable redesigned hooks while preserving the backend route and field assertions.

## Migration Plan

No migration is required. Deploy the Blade and CSS changes with the normal Vite build. Rollback is a file-level revert of the login view and login-specific CSS; no persisted data or authentication state is changed.

## Open Questions

None for this implementation pass.

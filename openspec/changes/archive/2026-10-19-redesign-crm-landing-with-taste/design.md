## Context

The existing Laravel 12 Blade welcome page uses Tailwind 4 and application theme tokens. Taste's contextual redesign rules guide the composition. The local Impeccable directory contains no skill files; supplied Impeccable principles and current PRODUCT.md, DESIGN.md, and app.css guide system fit.

## Goals / Non-Goals

Goals: a concise product-led entry page, asymmetric hero, illustrative workspace, clear workflow, accessible sign-in and theme controls, responsive light and dark presentation.

Non-goals: authentication changes, actual telephony interactions in the preview, fabricated proof, new fonts or dependencies, application-wide token changes.

## Decisions

- Retain Blade and the existing brand/icon components instead of introducing React into a static entry page.
- Use an asymmetric hero and restrained rose accent. Taste dials: variance 7, motion 3, density 4. Motion provides hover/press feedback; avoid continuous decorative movement.
- Use scoped `.landing-*` CSS imported by app.css; all surfaces and text use existing tokens. Keep established control/card radii.
- Make the workspace an explicitly labeled illustration, with fictional customer details and no focusable simulated controls. This explains actual supported workflows without exposing real customer information.
- Use semantic navigation, ordered workflow, role descriptions, skip link, visible focus, 44px targets, and reduced-motion rules.
- Preserve the existing theme key and dark fallback, while rejecting invalid stored values.

## Risks / Trade-offs

- Illustration could be mistaken for live data: label it as an illustrative workspace, keep decorative content hidden from assistive technology, provide an informative caption.
- Shared CSS could affect authenticated pages: scope every new selector to the landing surface.
- Local configured URL is offline: validate through an explicit local Artisan development server and record its URL.

## Validation

Update existing landing feature coverage and add configured-brand and login-target coverage. Run focused landing/auth/branding tests, build assets, and inspect Playwright at 375, 768, 1024, 1440px in both themes, including theme persistence, anchors, keyboard focus, reduced motion, console/network health, and sign-in navigation. Sync and archive only after validation.

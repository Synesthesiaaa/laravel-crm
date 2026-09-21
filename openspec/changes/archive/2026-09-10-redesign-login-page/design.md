## Context

The guest login view is a standalone Blade page that loads the shared `app.css` bundle, resolves the configured brand through `x-brand`, and supports a persisted light/dark theme. The current page already has the required username, password, optional campaign, status/error, CSRF, autofill, and theme behaviors, but its login-specific CSS leans heavily on decorative gradients, blur, animated sheen, and hover transforms.

The surrounding CRM uses a dark-first charcoal surface scale, DM Sans, and Signal Magenta as a scarce action/focus accent. The login surface is allowed to be more expressive than the authenticated shell, but it still needs to behave as an operational entry point for agents and supervisors, including long brand names, many campaigns, keyboard use, reduced motion, and narrow screens.

## Goals / Non-Goals

**Goals:**

- Give the login page a calm, modern composition with an obvious first action and a clear visual relationship to the CRM shell.
- Reuse the existing CSS custom properties for surfaces, text, borders, semantic feedback, and the primary action in both themes.
- Improve semantic grouping, spacing, focus treatment, responsive behavior, and purposeful interaction feedback without changing the login contract.
- Keep configured branding, campaign scope, validation/status feedback, theme persistence, and reduced-motion behavior intact.

**Non-Goals:**

- No authentication, rate-limiting, campaign-resolution, route, controller, API, migration, or dependency changes.
- No new product claims, registration flow, password-reset flow, social login, or additional guest navigation.
- No global redesign of the authenticated CRM shell or changes to the durable palette in `DESIGN.md`.

## Decisions

1. **Use a restrained two-zone entry composition.** On wide screens, the page will pair a quiet brand/context zone with the sign-in sheet; the form remains the dominant zone. On smaller screens, the context collapses into a compact brand header above the form. This creates hierarchy without adding a second visual language or extra workflow steps.

2. **Keep the auth sheet signature but reduce effect weight.** Retain the established auth radius and translucent entry treatment, replacing stacked gradients, inset highlights, animated sheen, and large glows with one subtle atmosphere, a tonal surface, a hairline border, and a shared elevation shadow. This preserves the login-specific glass identity while meeting the minimalism request.

3. **Use explicit form grouping and shared interaction states.** Add a named main landmark and small structural hooks for the form heading, campaign group, and action. Inputs/selects remain native controls with connected visible labels, 44px-or-greater targets, token-based focus rings, and semantic error/status containers. Avoid custom JavaScript for the form.

4. **Use a small motion grammar.** Keep a short entry reveal and theme icon feedback only when motion is allowed. Remove hover lift and button sheen; button hover changes tone and shadow only. The reduced-motion media query makes the page immediately readable and interactive.

5. **Keep CSS local to the login surface.** The implementation will update the existing login section in `resources/css/app.css` and the login Blade markup only, leaving shared tokens and unrelated dirty worktree changes untouched.

## Risks / Trade-offs

- [Risk] A two-zone desktop layout could feel too spacious on medium laptop widths → Mitigation: use a bounded max-width frame and collapse the context zone at the tablet breakpoint.
- [Risk] Translucent surfaces can reduce contrast in custom branding/theme combinations → Mitigation: keep text on the existing ink tokens, use a visible border/focus ring, and verify dark/light screenshots plus computed contrast in the browser.
- [Risk] Long configured brand names can widen the layout → Mitigation: constrain the brand text, allow wrapping, and test a long-name fixture at narrow widths.
- [Risk] Removing strong hover animation could make the submit state feel less responsive → Mitigation: retain color, border, shadow, focus, active, and disabled-state feedback with reduced-motion-safe transitions.

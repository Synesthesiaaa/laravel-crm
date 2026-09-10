## Why

The login page is the first contact with the CRM, but its current glass treatment uses several layered gradients, animated sheen effects, and a strong button glow that compete with the small credential task. A calmer, more intentional entry surface will align the authentication experience with the established Operational Signal Desk palette while making the form easier to scan and use across themes and screen sizes.

## What Changes

- Introduce a dedicated login-experience capability covering the guest authentication surface.
- Recompose the page around a restrained dark/light atmosphere and a compact, clearly prioritized sign-in sheet.
- Refine the brand block, heading hierarchy, field grouping, campaign selector, submit action, and theme toggle using the existing design tokens.
- Preserve campaign-aware login submission, configured branding/favicon behavior, validation/status alerts, browser autofill semantics, keyboard focus, reduced-motion support, and responsive behavior.
- Remove decorative motion and visual treatments that do not communicate state; retain only purposeful interaction feedback.

## Capabilities

### New Capabilities

- `login-experience`: A modern, palette-aligned, accessible guest login surface for credential and campaign selection.

### Modified Capabilities

<!-- No existing requirement changes; company branding and authentication behavior remain intact. -->

## Impact

- `resources/views/auth/login.blade.php` for semantic structure and presentation hooks.
- `resources/css/app.css` for login-specific layout, theme, responsive, focus, and motion styles.
- New feature-level OpenSpec requirements and design notes; no migrations, routes, APIs, dependencies, or authentication services change.
- Browser validation at mobile, tablet, and desktop widths, including dark/light theme and error/success states.

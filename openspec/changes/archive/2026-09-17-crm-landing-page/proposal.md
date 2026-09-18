## Why

The public root currently redirects directly to the private sign-in form, so visitors get no product context before authentication. A focused landing page can explain the CRM's real call-center workflow, show the product, and give users a clear path to sign in without inventing sales claims or adding a new acquisition flow.

## What Changes

- Replace the root redirect with a public, branded landing page.
- Present concise product positioning around campaign-aware CRM work, browser telephony, VICIdial/Asterisk integration, reporting, callbacks, attendance, and role-scoped operations.
- Use existing configured branding, shared design tokens, icons, and responsive accessibility patterns.
- Present a lightweight code-native product preview that mirrors the CRM's real lead, call, form, and disposition workflow.
- Keep the existing `/login` route and authentication behavior unchanged; the landing page's primary calls to action lead there.

## Capabilities

### New Capabilities

- `public-landing-experience`: A responsive, accessible public product page that explains the CRM accurately and directs users into the existing sign-in flow.

### Modified Capabilities

<!-- No existing capability requirements change. -->

## Impact

- `routes/web.php` for the public root route.
- `resources/views/welcome.blade.php` for the landing-page structure and copy.
- `resources/css/app.css` only if landing-specific presentation needs cannot be expressed with existing utilities/tokens.
- `tests/Feature/LandingPageTest.php` for public-root behavior and core conversion content.
- No migrations, APIs, dependencies, authentication logic, or telephony behavior change.

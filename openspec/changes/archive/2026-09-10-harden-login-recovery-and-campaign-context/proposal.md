## Why

The login surface is clear on the happy path, but users receive no immediate confirmation after submitting credentials and have limited guidance when authentication fails. The campaign selector also asks users to make a choice that is unnecessary when only one campaign is available, increasing friction at the point of entry.

## What Changes

- Add an accessible in-flight sign-in state that prevents duplicate submissions and announces progress.
- Add field-level recovery feedback, password visibility control, and concise supervisor/help-desk guidance while retaining privacy-safe credential errors.
- Render a single configured campaign as read-only context; retain the native selector when multiple campaigns are available.
- Preserve the existing login route, request fields, rate limiting, campaign fallback behavior, and authentication security boundaries.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `login-experience`: define sign-in progress, recovery guidance, password visibility, and single-campaign read-only context behavior.

## Impact

- `resources/views/auth/login.blade.php`: update form semantics, recovery affordances, campaign presentation, and lightweight login interaction JavaScript.
- `resources/css/app.css`: style progress, field feedback, password visibility, help guidance, read-only campaign context, and compact responsive states using existing tokens.
- `tests/Feature/LoginTest.php`: cover the new rendered states and single-campaign behavior.
- `openspec/specs/login-experience/spec.md`: sync the resulting login requirements.
- No database schema, public API, authentication route, or dependency changes are required.

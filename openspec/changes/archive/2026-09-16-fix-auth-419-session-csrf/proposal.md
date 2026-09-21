## Why

Browser login and logout submissions can reach Laravel's CSRF protection with a stale or invalid session token and currently fall through to the generic 419 Page Expired response. This leaves the user at an unrecoverable-looking error page even though a fresh authentication form can establish a valid token and session.

## What Changes

- Add browser-form recovery for CSRF mismatches on login, pending-login, and logout routes.
- Redirect failed guest authentication submissions to a fresh login form with safe non-secret input preserved and actionable feedback.
- Redirect an expired logout submission to the authenticated dashboard when the user session is still valid, or to login when it is not, with a retry message.
- Preserve the existing CSRF middleware and return the normal 419 response for JSON/API requests and unrelated routes.
- Add regression coverage for the auth-route recovery responses and the normal token/session login-to-logout flow.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `login-experience`: Authentication form submissions that encounter an expired browser session/token provide a recoverable form state instead of leaving the user at the generic 419 page.

## Impact

- `bootstrap/app.php` exception rendering for `TokenMismatchException`.
- Authentication feature tests covering login/logout token recovery.
- The existing login-experience OpenSpec requirement for recovery guidance.
- No route, database, dependency, or CSRF-exemption changes.

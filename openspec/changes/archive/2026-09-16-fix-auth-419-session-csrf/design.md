## Context

Laravel's web CSRF middleware correctly rejects a login or logout request when its hidden token does not match the current session. The current exception pipeline leaves browser form submissions at the generic 419 response, so users have no guided way to obtain a fresh token. The application already uses database-backed encrypted sessions, standard `@csrf` fields, and route-specific authentication flows; the change must preserve those security and compatibility boundaries.

## Goals / Non-Goals

**Goals:**

- Provide a recoverable browser flow for CSRF mismatches on the authentication form routes.
- Ensure a fresh GET of the login or authenticated dashboard renders the current session token for retry.
- Preserve safe form context for guest login recovery without flashing passwords or CSRF tokens.
- Keep JSON/API and unrelated 419 responses unchanged.
- Add focused tests for the exception responses and the ordinary regenerated-session token flow.

**Non-Goals:**

- Do not exempt login, pending-login, or logout routes from CSRF validation.
- Do not retry a rejected POST automatically or weaken session regeneration/invalidation.
- Do not change session storage, cookie configuration, routes, credentials, or deployment environment values.
- Do not redesign the login page or introduce a new visual language.

## Decisions

### Handle the typed CSRF exception at the application exception boundary

Register a `TokenMismatchException` render callback in `bootstrap/app.php`. It can distinguish the matched route and request type before producing a response, while leaving the framework's default 419 handling intact for APIs and non-auth pages. This is preferred over a global middleware because it keeps CSRF verification and the authentication middleware order unchanged.

### Redirect only browser form auth submissions

For login and pending-login routes, redirect to the named login route, flash a safe error, and preserve only non-secret input. For logout, redirect authenticated users to the dashboard so its newly rendered form contains a usable token; redirect unauthenticated users to login. JSON requests and unrelated routes return `null` from the callback so Laravel continues with its normal 419 response.

### Treat the fresh GET as the token recovery step

The redirected GET runs the normal session middleware and renders a token from the current session. This accommodates expired database sessions, session-id rotation after login, browser back-button forms, and stale tabs without manually accepting the rejected token or persisting a second token source.

## Risks / Trade-offs

- [A stale token can be a symptom of inconsistent production `APP_KEY`, cookie domain, host, or shared-session configuration.] The recovery improves the user-facing outcome but does not repair deployment configuration; those values must still remain stable across requests and nodes.
- [A redirect adds one request before retrying.] This is intentional because a fresh GET is required to issue the current form token safely.
- [Session flash data may not be persisted in every exception-unwind path.] The redirect remains useful without the message because the fresh GET is the recovery mechanism; tests assert the destination and security boundary rather than relying only on flash rendering.

## Migration Plan

No database or configuration migration is required. Deploy the exception-handler and tests with the application code. Rollback is a code revert; existing CSRF rejection and default 419 behavior are otherwise unchanged.

## Open Questions

None.

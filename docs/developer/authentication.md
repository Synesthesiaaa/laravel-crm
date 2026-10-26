# Authentication

## Browser session flow

[LoginController](../../app/Http/Controllers/Auth/LoginController.php) accepts required string `username` and `password` and optional string `campaign` through `LoginRequest`. Credentials are checked through `AuthService`/`UserRepository`. Failed attempts increment a username+IP limiter (five attempts, 900-second decay); the route additionally has the `login` limiter (five requests per minute per IP).

When another database session exists, credentials produce a five-minute `login_pending` session payload and redirect to `/login/pending`. Confirmation validates expiry and account existence, then signs in and removes other database sessions. Cancellation clears the pending payload. Remember-me is disabled. Finalization logs attendance, resolves campaign, stores telephony bootstrap data, regenerates the session, and redirects to the intended dashboard.

Logout is POST with session auth and CSRF. `AuthService` completes local active calls, marks VICIdial state logged out locally, closes attendance status, records logout, invalidates the session, and regenerates the CSRF token. A local logout flag does not prove external agent logout or SIP deregistration.

## Session JSON APIs versus Sanctum

`/api/...` in `routes/web.php` is a URL convention, not a token-authentication boundary. These endpoints use `web`; authenticated routes require a valid browser session and state-changing methods require CSRF. Send `Accept: application/json` for JSON auth/validation failures. Without it, web authentication may redirect. Only AMI and VICIdial event POST webhooks are explicitly CSRF exempt.

`routes/api.php` exposes GET `/api/user` and GET `/api/v1/disposition-codes` with `auth:sanctum`. Bearer tokens can be used here; the routes do not define a token-issuance API. There is no broad public token API matching the browser route inventory. Do not assume middleware for stateful first-party Sanctum authentication has been enabled simply because Sanctum is installed.

Default framework error behavior includes unauthenticated JSON 401, forbidden 403, validation 422, CSRF 419, and limiter 429. Browser login/logout CSRF failures have explicit redirect recovery in `bootstrap/app.php`; JSON requests keep normal error behavior. Controller-specific errors are documented in the [API reference](api-reference.md).

## Public and machine ingress

Operations insights requires an authenticated Team Leader, Admin, or Super Admin session. Health endpoints remain public diagnostic surfaces. AMI and VICIdial event webhooks require configured shared secrets in all environments. Call URL GET callbacks require a nonempty shared `sig` in all environments. Signed unsubscribe routes use signed URL validation. See [authorization](authorization.md) and [integrations](../integrations/README.md).

[Authentication diagram](../diagrams/authentication.md)

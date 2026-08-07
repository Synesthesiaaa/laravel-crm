## Context

The application already uses Spatie Activitylog, an `ActivityObserver`, a normalized `ActivityLogEntry` service, and a private realtime Activity Log channel. Existing model traits and explicit services cover only selected state changes. Laravel 12 applies the `web` and `api` middleware groups automatically to the application's route files, making them the central boundary for capturing all authenticated HTTP activity.

## Goals / Non-Goals

**Goals:**

- Capture every authenticated web and API request, including read-only pages, polling, successful actions, redirects, validation failures, and authorization failures.
- Attribute each request to the authenticated user when available, including logout requests by capturing the user before the route runs.
- Store only a bounded, redacted request summary; never persist request bodies, passwords, tokens, or credentials.
- Reuse the current activity observer, normalized history endpoint, realtime broadcast, retention cleanup, and terminal UI.

**Non-Goals:**

- Capturing unauthenticated requests or failed login attempts without a known user.
- Replacing Laravel application logs, security logs, or telephony logs.
- Capturing full request/response bodies or sensitive headers.

## Decisions

1. **Use middleware at the `web` and `api` group boundary.** This provides complete coverage without manually editing every controller. The middleware records after `$next` returns so it can include the HTTP status code, while retaining the pre-request user for logout.

2. **Use a dedicated `UserActivityRecorder` service.** The middleware remains orchestration-only. The service builds the request property payload, applies `ActivityLogSanitizer`, records the activity with `causedBy`, and catches audit failures so logging never changes the user's response.

3. **Represent requests as `log_name=request`, `event=request`.** The normalized entry exposes the HTTP method as the terminal action and includes request metadata separately. Existing model/domain events keep their current event names and before/after changes.

4. **Register the middleware with `web(append: [...])` and `api(append: [...])`.** This follows Laravel 12's supported middleware-group configuration and ensures the request user/session is available. Internal unauthenticated requests are naturally ignored; authenticated broadcast authorization requests are included as requested.

5. **Keep the existing Super Admin visibility boundary.** All users' records are stored and can be filtered by actor, but only Super Admins can access the Activity Log page and history endpoint.

## Risks / Trade-offs

- **[Risk]** Request volume may grow rapidly because polling is included. → Reuse the existing activity retention policy and add a created-at index already used by bounded history queries.
- **[Risk]** A logging/database failure could break normal application requests. → Catch recorder exceptions and write a compact warning to the audit channel.
- **[Risk]** Query strings may contain secrets. → Run all query metadata through the existing recursive sanitizer and omit request bodies and headers.
- **[Trade-off]** The terminal will contain infrastructure polling entries as requested. → Expose the request method/path/status clearly so operators can distinguish polling from business actions.

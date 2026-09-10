## Context

The CRM records login and logout security activities through Spatie Activitylog. `ActivityObserver::created()` dispatches `ActivityLogCreated`, which implements `ShouldBroadcastNow` and `ShouldDispatchAfterCommit`. With Reverb configured but not listening on the local port, the broadcast is attempted when the transaction commits and the Pusher-compatible client throws after the observer's dispatch call has returned. That exception currently escapes the request and prevents authentication from completing.

The broadcast is supplemental: the database activity row and the authentication/session transition are the source of truth, while the activity-log page already has a polling fallback. Local development currently starts the application, queue, log, and Vite processes together but omits the Reverb server even though `.env` points the broadcaster and Echo client at Reverb.

## Goals / Non-Goals

**Goals:**

- Keep login and logout successful when the optional realtime broadcaster is stopped, misconfigured, or temporarily unreachable.
- Preserve activity persistence, event channel authorization, payload shape, after-commit ordering, and exception reporting.
- Add deterministic regression coverage without making tests depend on a real WebSocket process.
- Make the documented all-in-one local development command start Reverb with the existing configuration.

**Non-Goals:**

- Do not change the authentication routes, credentials, session invalidation, or security activity records.
- Do not globally swallow exceptions from unrelated broadcasts or change the configured production broadcaster.
- Do not add a new dependency, queue architecture, retry policy, or database migration.
- Do not make the application process manage a production Reverb service.

## Decisions

### Make the activity broadcast explicitly rescuable

Add Laravel 12's `Illuminate\Contracts\Broadcasting\ShouldRescue` contract to `ActivityLogCreated`. Laravel's `BroadcastManager` wraps both immediate and queued broadcasts for rescuable events with the framework `rescue` helper, which reports the exception and lets the original request continue. This is placed on the single observer-generated activity event because it is the non-critical side effect responsible for the authentication failure.

The existing `ShouldBroadcastNow` and `ShouldDispatchAfterCommit` contracts remain unchanged. The event will still broadcast immediately after a successful transaction commit when Reverb is available, and its private channel and normalized payload remain intact. The observer's existing catch remains useful for synchronous dispatch/setup failures and audit logging; it is not treated as the boundary for after-commit failures.

**Alternative considered:** wrapping the whole login/logout service in a broad `try/catch` would protect the request but would hide the failure boundary from other activity-producing requests and could accidentally swallow authentication errors. Changing the default broadcaster to `null` would avoid the exception but disable realtime behavior even when Reverb is running.

### Start Reverb in the local composite command

Extend the existing `composer dev` `concurrently` command with `php artisan reverb:start` and a matching process name/color. Reverb reads `REVERB_SERVER_PORT` from the existing application environment, so no hard-coded port or new environment variable is introduced. Production remains responsible for supervising its own Reverb process as documented.

**Alternative considered:** starting Reverb from an application service provider would couple HTTP requests to a long-lived process and is unsafe under Apache, PHP-FPM, or multiple workers. Requiring a separate manual command preserves behavior but leaves the advertised all-in-one workflow incomplete.

### Test the failure mode without a network dependency

Add a unit assertion that the event implements `ShouldRescue`, plus feature tests that set `broadcasting.default` to an undefined connection and exercise login and logout. The invalid connection fails before any network request and verifies the complete authentication boundary: the response redirects normally, the expected user state is reached, and the activity/attendance behavior still executes.

## Risks / Trade-offs

- [Realtime updates are missed while Reverb is unavailable] → Keep persisted activity as the source of truth and retain the existing polling fallback; report the exception for operations visibility.
- [The local composite command starts one more long-lived process] → Give it an explicit process name and use the existing `concurrently --kill-others` lifecycle.
- [A broadcaster configuration error could become less visible to the user] → Laravel's rescue helper reports the exception, and the existing audit warning path remains in place for dispatch failures.
- [A future critical event might be marked rescuable accidentally] → Scope the contract to `ActivityLogCreated` and cover the intended non-blocking behavior in tests.

## Migration Plan

1. Add `ShouldRescue`, regression tests, and the Reverb process to the local composite command.
2. Run the focused login/activity tests, Pint, Composer validation, and the frontend build if the working tree's frontend changes require it.
3. For local realtime operation, restart `composer dev` so the new Reverb process starts; existing separately managed deployments continue using their supervisor/PM2 process.
4. Rollback by removing the contract, tests, and composite-command entry. No data migration or persisted-data rollback is required.

## Open Questions

None.

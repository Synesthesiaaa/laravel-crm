## Why

Login and logout currently perform security activity logging inside the authentication flow. The activity observer dispatches a realtime event after the database transaction commits, so an unavailable local Reverb server can surface as a `BroadcastException` and turn a successful authentication action into a 500 response.

## What Changes

- Make the activity-log realtime event rescuable so a WebSocket outage is reported without blocking login, logout, request completion, or activity persistence.
- Add regression coverage proving login and logout complete when the configured broadcaster is unavailable.
- Start Laravel Reverb from the existing all-in-one local development command when realtime broadcasting is configured.

## Capabilities

### New Capabilities

- `resilient-authentication-broadcasts`: Authentication and activity logging remain usable when the optional realtime broadcaster is unavailable.

### Modified Capabilities

<!-- No existing capability requirements are changed; this adds resilience to the authentication side effect. -->

## Impact

- `app/Events/ActivityLogCreated.php` changes its broadcast failure behavior without changing the channel or payload.
- `tests/Unit/Events/ActivityLogCreatedTest.php` and `tests/Feature/LoginTest.php` gain focused regression assertions.
- `composer.json` starts `php artisan reverb:start` alongside the existing local app, queue, log, and Vite processes.
- No new dependencies, routes, database changes, authentication fields, or production configuration changes are required.

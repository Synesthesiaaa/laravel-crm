## Context

The project has multiple operational entry points and queues, two Artisan commands sharing a signature, CI that can pass without satisfying its configured coverage threshold, role checks that consult different sources, and telemetry paths that discard failures. This change makes those paths explicit without changing production database records or weakening access control.

## Goals / Non-Goals

**Goals:** Make command registration unambiguous, ensure documented local workers consume active queue lanes after successful migration, enforce CI gates and frontend checks, test database contention on isolated MySQL, use the role value maintained by supported user administration consistently, and record bounded safe telemetry.

**Non-Goals:** Backfill or rewrite existing role assignments, change queue backend defaults, enable external services in CI, or collect client stack traces/customer payloads.

## Decisions

- Keep `ami:listen` assigned to the currently documented listener and give the second implementation a distinct namespace command. Update every script/documentation reference and assert both are discoverable.
- Startup scripts will migrate before serving or workers start, fail immediately on migration errors, consume general queues on Laravel's configured default connection, and pin only the durable sync lane to the `database` connection. Email worker startup remains explicit opt-in so messaging stays fail-closed. Preserve shell-specific behavior and avoid masking failures.
- CI will fail if coverage is not generated or meets its threshold, retain the coverage artifact, and run pinned npm install, JS tests, and asset build. The MySQL concurrency test will run only against the workflow's ephemeral service and be separated from SQLite tests.
- Use `users.role` consistently for the already-supported role administration path. Do not mutate Spatie pivot data or production users without a separate verified data audit.
- Validate client-error payload shape, apply route throttling, and log only bounded event categories and safe metadata. Capture throttle exceptions at the exception boundary because middleware after a throwing throttle cannot observe the response.

## Risks / Trade-offs

- [Startup ordering can expose missing queue configuration] → Validate each queue profile against existing config and document exact commands before replacing scripts.
- [Coverage gates may surface existing untested files] → Keep the configured threshold and add targeted coverage; do not add an ungated fallback.
- [Role pivot data may differ from `users.role`] → Do not rewrite it; document `users.role` as the supported authority and avoid inferring unverified legacy assignments.
- [Telemetry can expose user data] → Exclude raw message, stack, request body, tokens, and customer data; validate and bound all fields.
- [MySQL contention can be timing-sensitive] → Use barriers/independent connections and assert persisted invariants, not wall-clock order.

## Migration Plan

No production data migration is required. Deploy command/script/CI/telemetry changes with the application. CI database contention uses a fresh workflow service; application data remains untouched. Rollback consists of reverting code and scripts; no persistent schema change is introduced by this change.

## Open Questions

- Email campaign workers remain opt-in through `START_EMAIL_WORKER=1`; Phase 3 outbox jobs use the `database` connection and `telephony-sync` queue in local profiles and CI.
- The declared PHP floor and CI matrix match the updated Composer lock; the local Boost runtime reports PHP 8.5 and Laravel 12.70.1.

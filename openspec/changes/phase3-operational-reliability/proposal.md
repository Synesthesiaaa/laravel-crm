## Why

The audit confirms ambiguous AMI command registration, incomplete startup queue profiles and migration ordering, a coverage gate that can fall back to an ungated pass, sequential tests described as concurrency coverage, inconsistent role sources, and failure telemetry that is discarded or placed behind middleware that never sees throttling exceptions.

## What Changes

- Give the two AMI listener implementations distinct command names and document which process profile uses each.
- Run migrations successfully before serving and start workers for the queues actually used by telephony, imports, legacy Asterisk, and email.
- Make PHP coverage an enforced gate, publish its artifact, and run the JavaScript test and asset-build checks in CI.
- Describe sequential lifecycle checks accurately and add isolated multi-connection MySQL contention coverage.
- Use the `users.role` value written by supported user administration as the shared role authority without rewriting existing role data.
- Record bounded, sanitized client-error and throttle telemetry at the route and exception boundaries where failures are actually observable.

## Capabilities

### New Capabilities

- `operational-process-reliability`: Define worker startup, command registration, CI gates, role authority, and bounded failure telemetry.

### Modified Capabilities

- `platform-stabilization`: Require unambiguous AMI command registration and observable, sanitized request failures.
- `runtime-compatibility`: Enforce the CI coverage gate and frontend verification steps.
- `call-history-sync-reliability`: Ensure documented local worker profiles consume every enabled queue lane.

## Impact

Affected areas include Artisan command signatures, Windows and Unix startup scripts, queue configuration and documentation, GitHub Actions, user role middleware, client-error validation and logging, exception rendering, and focused command/authorization/telemetry tests. CI contention tests use only its isolated MySQL service; no configured application or production database is used.

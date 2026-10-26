## Why

The current application exposes Operations Insights and customer records to guests, allows lead-tool input to replace privileged VICIdial transport parameters, and can log credentials embedded in transport exceptions. Both public telephony webhooks also accept unauthenticated requests when their secrets are unset outside production. The committed dependency graph has active security advisories, while the PHP manifest and CI matrix claim support for versions the lockfile cannot install.

## What Changes

- Restrict the Operations Insights page and all of its data endpoints to authenticated users in the existing campaign context and the established Team Leader, Admin, and Super Admin reporting roles.
- Ensure lead requests cannot choose VICIdial operations or override server-controlled credentials and source parameters through either Agent or Non-Agent API transport.
- Redact credentials from telephony exception text and nested logging context before it reaches any telephony log channel.
- Require configured shared secrets for AMI and VICIdial event POST webhooks in every runtime environment; use explicit synthetic secrets in webhook tests.
- Upgrade vulnerable locked Composer and npm packages within compatible declared constraints, without forced major upgrades.
- Declare PHP 8.4 as the minimum supported version, align CI and installation requirements, and keep platform requirement failures visible.

## Capabilities

### New Capabilities

- `operations-insights-access`: authenticated, role-gated access to aggregate reporting and configured customer records.
- `runtime-compatibility`: supported PHP baseline and audited, reproducible locked dependency versions.

### Modified Capabilities

- `telephony-operations-and-reporting`: reserve VICIdial transport operation and credential parameters and redact transport failures.
- `platform-stabilization`: require valid webhook authentication in every runtime environment.

## Impact

- Affected routes, `OperationsInsightsController`, lead request/controller/service paths, Agent and Non-Agent VICIdial transports, `TelephonyLogger`, webhook controllers, Composer/npm lockfiles, PHP/CI declarations, and runtime/webhook documentation.
- Existing authenticated supervisor reporting and campaign behavior remains available. Guest access to Operations Insights is intentionally removed because it includes customer-level data.
- No schema or production-data changes are planned. Tests use synthetic records, fake HTTP responses, and synthetic webhook secrets; they must not contact VICIdial/Asterisk or send calls, SMS, or email.

## Acceptance Criteria

- Guests cannot retrieve the Operations Insights page, aggregate report data, or customer records; the existing authorized reporting roles retain the page and its campaign/field filtering.
- Untrusted lead input cannot replace `function`, `source`, `user`, `pass`, or other Agent API transport-owned parameters, and invalid reserved parameters do not produce a remote request.
- Telephony logs and returned transport metadata do not contain sentinel credentials from query URLs, exception messages, or nested context.
- Both POST webhooks reject missing configuration in testing, staging, and production; missing configuration returns a clear service-unavailable response, invalid credentials return unauthorized, and the correct synthetic secret still processes a fixture.
- Composer and npm audits report no remaining advisories for locked packages after compatible updates, or any unfixable advisory is documented with its exact package/version and constraint.
- Composer declares `^8.4`; CI installs and runs on the supported PHP matrix without `--ignore-platform-reqs`; installation documentation agrees with the lockfile.

## Validation

- Add focused feature and unit tests for guest/role access, reserved transport parameters, secret redaction, and webhook fail-closed behavior.
- Run the focused PHP tests, relevant broader regression tests, Pint, Composer validation/platform checks, Composer and npm security audits, and the frontend build if dependency changes affect it.
- Use Playwright against an isolated local/test database for the guest and authorized Operations Insights flow when the test runtime can be started without using production services or data.
- Review the diff and OpenSpec validation; do not archive this change until all acceptance criteria and applicable browser checks are complete.

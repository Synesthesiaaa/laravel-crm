## Context

Operations Insights currently sits outside the authenticated campaign route groups and returns configured Data Master records. Lead API controllers validate a small request subset but forward the full request into services that merge caller parameters after VICIdial operation and credential parameters. The Agent API transport has the same merge-order weakness, and its connection exceptions can contain the GET query with user credentials. Both event POST webhooks are public and only reject missing secrets in `production`.

The lockfiles also contain current advisory matches. Composer declares PHP `^8.2` and CI installs PHP 8.2/8.3, although the locked Symfony 8 packages require PHP 8.4. The application is a Laravel 12 monolith with existing reporting roles, campaign middleware, telephony transports, feature tests, and OpenSpec contracts; these should remain the implementation boundaries.

## Goals / Non-Goals

**Goals:**

- Close public customer-data access using the application's existing authenticated reporting route pattern.
- Make operation names, transport credentials, source identifiers, and user/session identities immutable at VICIdial boundaries.
- Keep secret values out of telephony logs, including nested context and credential-bearing exception URLs.
- Reject public webhook requests when authentication is not configured, independent of `APP_ENV`.
- Resolve current lockfile advisories through compatible updates and make the PHP baseline consistent across Composer, CI, and setup guidance.
- Add synthetic regression tests that do not call telephony or send messages.

**Non-Goals:**

- Changing campaign membership semantics, remote VICIdial permissions, APIs, telephony workflows, or lead field meanings beyond rejecting transport-owned controls.
- Changing database schemas, migrating or deleting customer records, inspecting retained production logs, or exercising remote systems.
- Addressing S5/S6, D3-D5, telephony state correctness, form integrity, frontend lifecycle, queue profiles, or performance findings; those belong to later audit phases.
- Moving the monolith to another framework or adding a new security dependency.

## Decisions

1. **Use the established reporting boundary.** Place all three Operations Insights routes inside the existing `auth` and `campaign` group and the current `Team Leader`, `Admin`, and `Super Admin` route role middleware. This matches the existing Reports page policy and protects both aggregate and row-level endpoints. A new policy abstraction would add a second authorization convention without evidence that a separate grant is already modeled.

2. **Validate route payloads and enforce reserved keys again at transports.** Lead controllers will pass FormRequest-validated input. FormRequests will prohibit caller-supplied operation, credential, source, agent identity, and campaign-routing controls. Both transport services will reject reserved parameters case-insensitively before sending HTTP, including nested Agent API query fields and Non-Agent batch requests. The transport layer remains the final boundary for internal callers.

3. **Redact at the telephony logger boundary.** Recursively replace values held under credential-like keys and redact credentials in URL query strings and message text before writing to any telephony channel. This covers the Agent transport without relying on every caller to remember a specialized sanitizer. No existing production logs will be read or rewritten.

4. **Fail closed for missing webhook configuration.** Both POST webhook controllers will return 503 when their configured shared secret is empty and 401 when the supplied header does not match. A valid secret retains the current event-processing contract. Tests set synthetic secrets explicitly.

5. **Update locked packages within their current major constraints.** Use targeted Composer updates for the packages in the current advisory report and `npm audit fix` without `--force`. Review the resulting lockfile diff and audit again; preserve unresolved advisory details instead of suppressing them.

6. **Adopt PHP 8.4 as the declared baseline.** The locked graph already requires it. Set `composer.json`, the CI test matrix/lint/audit jobs, and installation requirements to PHP 8.4 or 8.5. Do not ignore platform requirements or claim that the Windows runtime supplies Horizon's Unix extensions.

7. **Make the PAMI compatibility patch reproducible.** Install the locked PAMI source revision so Composer Patches can apply the repository's PHP-compatible `implode` fix on Windows and Unix. Set Composer to fail if any tracked patch cannot be applied; keep all other packages on distribution installs.

## Risks / Trade-offs

- **Webhook events stop in any environment missing a secret** → Update configuration examples and deployment guidance, and deploy configured random secrets to every environment before enabling inbound events.
- **Operations Insights is no longer public** → Tests verify the intended supervisor roles retain the page and data endpoints while guests and Agents are denied.
- **Package upgrades can alter framework, HTTP, or mail behavior** → Keep upgrades within current major constraints and run focused telephony, mail, route, test-suite, and asset-build checks; no real transport will be used.
- **PHP 8.2/8.3 deployments become unsupported** → State the minimum clearly and use the supported matrix in CI rather than hiding incompatibility with platform flags.

## Migration Plan

1. Set `ASTERISK_AMI_WEBHOOK_SECRET` and `VICIDIAL_EVENTS_WEBHOOK_SECRET` in each environment that receives POST events; configure VICIdial/Asterisk senders with the corresponding header.
2. Deploy the application and dependency lockfiles with PHP 8.4 or 8.5.
3. Verify guest denial, authorized reporting, synthetic webhook authentication, security audits, and queue/build health in an isolated environment.
4. Roll back the release artifact if application checks fail. Do not roll back by making customer data public or accepting unauthenticated webhook events.

## Open Questions

- None for this phase. Campaign membership remains governed by current campaign resolution and is not assumed to be per-user authorization.

## Verification Record

- Focused PHP validation passed: 38 tests, 202 assertions across Operations Insights access, lead transport protection, webhook authentication, telephony transport guards, and logger redaction.
- Pint passed on all 16 changed PHP files. The Vite production build passed. Composer and npm audits reported no advisories for their lockfiles.
- An isolated Playwright run against a temporary migrated SQLite database confirmed that a guest page request ends at `/login`, the JSON data endpoint returns 401, and the browser reports no console errors. The temporary database was removed.
- `composer check-platform-reqs --lock` passed PHP 8.5 and reported only missing `ext-pcntl` and `ext-posix` on this Windows host; those Unix extensions are required by Horizon. CI now provisions and checks both on Ubuntu. The hosted CI workflow itself was not run in this local session.
- The composer manifest validator still warns about pre-existing unbounded or exact version constraints for Pusher, Symfony CSS Selector, and TCPDF. These are outside the verified Phase 1 findings and remain for dependency-policy review.

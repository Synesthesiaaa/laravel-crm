# Production configuration

This is a source-based preparation checklist, not an executed deployment certification. Use it with [deployment](deployment.md) and [configuration](../getting-started/configuration.md).

## Baseline

Set `APP_ENV=production`, `APP_DEBUG=false`, a canonical HTTPS `APP_URL`, and the intended `APP_TIMEZONE`. Preserve the original `APP_KEY` and securely back it up; new key generation breaks access to encrypted values. Match HTTP and CLI PHP versions/extensions to the lockfile.

Use dedicated database credentials, database sessions, secure/HTTP-only cookies, and a deliberate SameSite/domain/path configuration. The default database-session workflow deletes other session rows on login; switching to Redis sessions does not provide an equivalent replacement guarantee. Redis remains appropriate for configured queues/cache/Horizon without changing the session driver.

Serve only `public/`, allow writes only to required storage/cache paths, and keep private email files outside public storage. Align the web process, scheduler, AMI listener, email worker, and general consumers on runtime account, environment, release, encryption key, and cache prefix.

## External systems

- Assign campaign-linked VICIdial servers and explicit remote campaign mappings. Configure both Agent and Non-Agent API paths for their respective uses. Test reachability/permissions in staging with authorized accounts.
- Configure AMI credentials, network restrictions, shared webhook secrets, SIP/WSS certificates, and one browser audio owner. CRM agent-channel configuration is SIP-only in current code.
- Keep certificate validation enabled. Install the appropriate trusted CA chain instead of disabling TLS verification to make a connection work.
- Reverb needs its own secret, public connection parameters, and an internal listener/proxy. Build `VITE_*` values into the release; they are public.
- Keep email live/payroll gates disabled until transport, recipient-document mapping, preview, worker and recovery procedures are accepted. Saved SMTP configuration and frozen campaign transport can override expectations based on `.env` alone.

## Exposure review

`/operations-insights`, its two JSON endpoints, and telephony/WebSocket health endpoints have no login middleware. They are not automatically protected by CRM roles. Decide and implement appropriate infrastructure access restrictions before exposure; this documentation does not add application protection. Review [security](security.md) and [API contracts](../developer/api-reference.md).

## Activation criteria

Before going live, verify route/permission behavior with synthetic accounts for all four roles, campaign mapping, form submission/extraction, backup restore, process supervision, scheduler tasks and deletion policy, remote call lifecycle, realtime authentication, and the organization's approved email tests. Record exact runtime/provider/PBX versions and observations. No blanket production-readiness claim follows from a passing `/up` response.

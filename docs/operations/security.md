# Security and access boundaries

This guide describes current source behavior and operational precautions. It is not a penetration test or a production compliance certification. Application defects identified during documentation work are recorded in the [review findings](../audit/documentation-modernization.md), without changing code.

## Login and authorization

Staff authenticate with username/password and a browser session. Login has an IP route limit and failed-credential username/IP throttling. Signing in elsewhere can replace other database session rows after confirmation. Keep `SESSION_DRIVER=database` for this implemented workflow; using another driver does not make its database queries disappear.

`CheckRole` compares `users.role` with exact role names. Spatie permissions are also seeded, and policies/requests add checks, but a seeded permission does not bypass a route requiring Super Admin. Refer to the [permission matrix](../roles-and-permissions.md) and [authorization guide](../developer/authorization.md) before granting access. In particular, Team Leader navigation does not imply every write on that page is permitted.

Campaign resolution selects context from the session or a fallback; it does not enforce a per-user campaign membership list. Do not advertise it as strict multi-tenant isolation. Individual queries/controllers have additional scoping rules, so investigate access at the exact endpoint. Reporting-oriented role labels in Operations Insights are not additional authentication roles.

## Public and incoming surfaces

- Operations Insights and its JSON endpoints require an authenticated Team Leader, Admin, or Super Admin session. Their JSON endpoints also have request throttling.
- `/api/telephony/health` and `/api/websocket/health` are public diagnostic surfaces. Health can expose operational/configuration information and contact an external server.
- The two POST AMI/VICIdial event webhooks bypass CSRF and require a configured shared secret plus a matching `X-Webhook-Secret` in every environment. Missing configuration returns 503; a missing or incorrect header returns 401.
- VICIdial GET Call URL callbacks require the configured `sig` shared value. It is not a per-message HMAC or timestamped replay guarantee. Protect URLs from access-log disclosure and replay at the network/integration layer.
- Email unsubscribe uses signed URLs and a confirmation POST. A valid signed URL is an authorization capability; do not publish it in examples or logs.

Review the [API reference](../developer/api-reference.md) for exact routes. Before deployment, restrict operational endpoints at the trusted reverse proxy/VPN according to organizational policy. Documentation does not add those protections. Never disable CSRF, role checks, signature checking, or TLS validation as a troubleshooting fix.

## Data and secrets

Protect `.env`, `APP_KEY`, database backups, worker environment, VICIdial/SIP/SMTP credentials, signed URLs, customer records, and private PDFs. Some legacy VICIdial passwords remain readable by a compatibility cast until rewritten; do not mistake that path for proof that all stored legacy data is encrypted. Preserve the application key for decrypting current encrypted credentials and email snapshots.

Only publish intended branding assets. Private email documents must remain on private storage with authenticated, no-store download routes. Confidential mappings must match an authorized recipient to the exact file. Payroll is blocked by the default password policy because uploaded originals cannot satisfy it; do not disable a required policy to force a send. See [document-delivery controls](../EMAIL_CAMPAIGNS.md).

Retaining or deleting a file is different from retaining recipient metadata, activity history, transport snapshots, backups, or provider copies. A retention job is not a complete privacy-erasure workflow. Establish appropriate retention and access procedures for all stores.

## Operational controls

Use HTTPS, trusted PBX certificates, least-privilege API/AMI/database accounts, restricted network paths, and one intended browser audio owner. Serve only `public/`; prevent direct web access to storage/configuration. Keep `APP_DEBUG=false` in production. Do not put secrets in `VITE_*` variables because the asset build publishes them.

Run services under deliberate accounts with shared access only where required. Validate private downloads and exports with synthetic low-privilege users. Treat CSV exports and job/log payloads as sensitive; sanitize before public issue reporting. Never run a bulk retry of uncertain email jobs without reconciliation.

Related: [production configuration](production-configuration.md), [backup/recovery](backup-and-recovery.md), [retention administration](../admin/retention-and-activity-log.md), [maintenance](maintenance.md).

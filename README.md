# Laravel CRM

A campaign-aware customer relationship management platform for call-center teams, built with Laravel.

Keep customer information, campaign forms, call outcomes, and attendance together so agents can work consistently and managers can review the results. Telephony connects to separately configured VICIdial and Asterisk systems.

## At a glance

A CRM helps a team remember who it contacted, what happened, and what should happen next. Laravel CRM organizes this work into campaigns: named work areas with their own forms and reporting context. Agents sign in, confirm their campaign, handle customer information, and record interaction outcomes. When telephony is enabled and provisioned, browser phone and lead tools support that work in the same workspace.

Team Leaders and administrators review attendance, records, agent activity, and reports. Super Admins set up accounts, campaigns, forms, integration servers, branding, and retention. A separate email workflow lets Super Admins prepare templates, import authorized recipients, review individual document assignments, and queue messages through a dedicated worker.

The application distinguishes local CRM records from external phone and email-provider state. A saved form, queued job, or accepted email does not prove a completed call or confirmed inbox delivery. Read the feature and access guides before treating an integration as ready for live work.

## Key capabilities

| Area | What it helps you do |
| --- | --- |
| Campaigns and forms | Organize work, configure data capture, and collect consistent information. |
| Agent workspace | Inspect leads, use enabled phone tools, save outcomes, and arrange callbacks. |
| Records and extraction | Review form/capture/call records and export authorized data to CSV. |
| Supervision and reports | Review staff activity, source-qualified call metrics, and configured CRM sales measures. |
| Attendance and notifications | Track login/status history and read recent operational messages. |
| Email and documents | Prepare recipient campaigns, validate PDF mappings, review drafts, and monitor sending. |
| Administration | Maintain accounts, routing, fields, branding, retention, and activity logs. |
| Integration services | Connect VICIdial APIs, AMI events, SIP/WebRTC media, queues, and Reverb updates. |

See the [verified feature catalog](docs/features.md) for access, dependencies, and limitations.

## How work flows

Sign in with a username/password and campaign choice → confirm campaign context → open the relevant lead/form tools → handle the interaction using configured phone services when needed → save the form or capture → record the disposition → review records and reporting.

These are distinct saves and external actions, not one transaction. Agent Screen access defaults off; CRM login does not establish a working phone session. Follow the [agent working-day guide](docs/user-guide/daily-workflow.md).

## Who uses it?

- **Agent:** handles customer interactions, forms, outcomes, callbacks, and personal attendance.
- **Team Leader:** monitors activity and attendance and uses permitted record/reporting tools.
- **Admin:** uses management tools and additional field/disposition configuration writes.
- **Super Admin:** manages restricted setup, user access, integrations, email, and system configuration.

Some setup routes remain Super Admin-only even when an Admin has a similarly named seeded permission. QA and Operations Manager are organizational responsibilities, not formal account roles. See the [permission matrix](docs/roles-and-permissions.md).

## Technology

Laravel **12.53.0**, Blade, Alpine.js **3.15.8**, Tailwind CSS **4.2.0**, Vite **7.3.1**, SIP.js **0.21.2**, Horizon **5.45.0**, Reverb **1.8.0**, and Sanctum **4.3.1** are present in the lockfiles. SQLite is used by tests; MySQL connections support the inspected application and telephony integration.

The Composer manifest requires **PHP 8.4+ within PHP 8.x**; the frontend lockfile requires **Node `^20.19.0 || >=22.12.0`**. Horizon needs Unix PCNTL/POSIX extensions. Read [requirements](docs/getting-started/requirements.md) before installation.

## Quick start

Use an empty local database with synthetic data and sending/calling disabled. Follow the copyable [quick start](docs/getting-started/quick-start.md) for dependency installation, environment setup, migrations, development seeders, assets, and first login. The [full installation guide](docs/getting-started/installation.md) covers Windows/Linux differences.

For a local database-backed worker, consume both the telephony and default queues: `php artisan queue:work database --queue=telephony,default --tries=1 --timeout=800`. Email campaign workers use the separate `email-campaigns` queue; see [queue and worker operations](docs/operations/queues-and-workers.md).

Do not apply development seeders, database resets, or new application-key generation to an existing production installation. For an existing deployment, use [maintenance](docs/operations/maintenance.md).

## Find your guide

| Reader | Start here |
| --- | --- |
| First-time visitor | [Overview](docs/overview.md), [features](docs/features.md), [use cases](docs/use-cases.md), [glossary](docs/glossary.md) |
| New staff member | [First login](docs/getting-started/first-login.md), [user guide](docs/user-guide/README.md), [FAQ](docs/faq.md) |
| Agent | [Daily workflow](docs/user-guide/daily-workflow.md), [calls and dispositions](docs/user-guide/calls-and-dispositions.md) |
| Team Leader or supervisor | [Supervisor and operations](docs/admin/supervisor-and-operations.md), [reports](docs/user-guide/reports.md) |
| Administrator | [Administration](docs/admin/README.md), [access rules](docs/roles-and-permissions.md) |
| Developer | [Architecture](docs/developer/architecture.md), [developer guides](docs/developer/README.md), [API reference](docs/developer/api-reference.md) |
| System operator | [Deployment](docs/operations/deployment.md), [workers](docs/operations/queues-and-workers.md), [security](docs/operations/security.md), [troubleshooting](docs/operations/troubleshooting.md) |

[Browse all documentation](docs/README.md), including preserved specialist guides and historical records.

## Project status and limits

This documentation describes source reviewed on **2026-10-26**; it is not a live-integration or production certification. Automated checks use synthetic data and do not establish deployment-specific access mappings, live VICIdial/Asterisk connectivity, email delivery, fresh installation, or restore readiness. Validate those operations in an approved non-production environment before enabling them for users.

Confidential email documents require correct recipient assignment and passing validation. Payroll remains blocked when required password protection cannot be satisfied. See [email controls](docs/EMAIL_CAMPAIGNS.md) and [known findings](docs/audit/documentation-modernization.md).

## Support and contribution

Use [troubleshooting](docs/operations/troubleshooting.md) first. When opening an issue in this repository's GitHub Issues, include a synthetic reproduction, release/runtime, expected result, actual result, and sanitized error. Never attach customer data, payroll documents, credentials, live phone numbers, or signed URLs.

For code changes, follow the [development workflow](docs/developer/development-workflow.md), [testing guide](docs/developer/testing.md), and [documentation maintenance checklist](docs/documentation-maintenance.md). Historical plans and screenshots are preserved; screenshots are not republished without verified synthetic provenance.

## License

`composer.json` declares MIT, but this checkout contains **no standalone LICENSE file**. That declaration does not establish a complete license notice for all application contributions or dependencies. The repository owner should confirm the intended terms and add the appropriate license text; do not infer application licensing from Laravel's license.

# Laravel CRM documentation

Choose a guide by the job you need to do. These guides describe source reviewed on **2026-10-15**; external services and deployment readiness require separate validation.

## Understand the product

- [Overview](overview.md): what the CRM does and who it serves.
- [Feature catalog](features.md): implemented capabilities and limitations.
- [Roles and permissions](roles-and-permissions.md): the actual four-role access map.
- [Use cases](use-cases.md), [glossary](glossary.md), and [FAQ](faq.md).

## Get started

1. Check [requirements](getting-started/requirements.md).
2. Follow the [quick start](getting-started/quick-start.md) or [full installation](getting-started/installation.md).
3. Review [configuration](getting-started/configuration.md).
4. Complete [first login](getting-started/first-login.md) and, for Super Admins, [first campaign setup](getting-started/first-campaign.md).

## Work with the CRM

| Reader | Guides |
| --- | --- |
| Agents | [Daily workflow](user-guide/daily-workflow.md), [campaigns/leads](user-guide/campaigns-and-leads.md), [forms/records](user-guide/forms-and-records.md), [calls/dispositions](user-guide/calls-and-dispositions.md), [callbacks](user-guide/callbacks.md), [attendance](user-guide/attendance.md), [notifications](user-guide/notifications.md) |
| Team Leaders | [Supervisor](admin/supervisor-and-operations.md), [reports](user-guide/reports.md), [data management](admin/data-management.md), [access map](roles-and-permissions.md) |
| Admins | [Administration home](admin/README.md), [forms/field logic](admin/forms-and-field-logic.md), [data management](admin/data-management.md) |
| Super Admins | [Users](admin/users.md), [campaigns](admin/campaigns.md), [configuration](admin/configuration.md), [retention/activity](admin/retention-and-activity-log.md), [email configuration](admin/email-configuration.md), [email campaigns](EMAIL_CAMPAIGNS.md) |

The [user-guide home](user-guide/README.md) and [administration home](admin/README.md) explain the navigation and access prerequisites. Several related subjects share one guide to keep workflows together and avoid contradictory copies.

## Build and integrate

- [Developer home](developer/README.md): architecture, project structure, database, authentication/authorization, services, realtime, tests, and contribution workflow.
- [API reference](developer/api-reference.md), [endpoint inventory](developer/endpoint-inventory.md), and [request/response contracts](developer/api-contracts.md).
- [Integration home](integrations/README.md): VICIdial, AMI, browser softphone, SIP/WebRTC, Reverb, and SMTP.
- [Source-backed diagrams](diagrams/README.md) and [synthetic examples](examples/README.md).

## Operate safely

- [Operations home](operations/README.md).
- [Deployment](operations/deployment.md) and [production configuration](operations/production-configuration.md).
- [Queues/workers/scheduler](operations/queues-and-workers.md) and [monitoring](operations/monitoring.md).
- [Security](operations/security.md), [backup/recovery](operations/backup-and-recovery.md), and [maintenance](operations/maintenance.md).
- [Troubleshooting knowledge base](operations/troubleshooting.md).

## Preserve context and keep docs current

| Existing resource | How to use it |
| --- | --- |
| [PRODUCT.md](../PRODUCT.md), [DESIGN.md](../DESIGN.md) | Product/design context; source wins on behavior and access |
| [INSTALLATION.md](../INSTALLATION.md) | Stable entry point to maintained setup guides |
| [MIGRATION.md](../MIGRATION.md), [EMAIL.md](../EMAIL.md) | Preserved historical notes with current-guide notices |
| [Email campaigns](EMAIL_CAMPAIGNS.md) | Maintained detailed email authority |
| [Asterisk specialist notes](asterisk) | Existing infrastructure recipes; start with current [AMI](integrations/asterisk-ami.md) and [SIP/WebRTC](integrations/sip-webrtc.md) guides before executing old commands |
| [Telephony phase documents](telephony) | Historical implementation/audit context; not proof of current deployment readiness |
| [Earlier audits](audit/README.md) | Historical findings and UI rules; review dates and source |
| [Superpowers plans/specs](superpowers) | Design intent/history, not a shipped-feature catalog |
| [OpenSpec](../openspec) | Requirements and change history; active proposals may be incomplete |
| [Existing screenshots](images) | Preserved only; synthetic provenance has not been established, so no operational screenshots are embedded in these guides |

See [documentation maintenance](documentation-maintenance.md) for the pull-request checklist, [documentation audit](audit/documentation-modernization.md) for contradictions and preserved resources, and [validation record](audit/documentation-validation.md) for checks actually performed.

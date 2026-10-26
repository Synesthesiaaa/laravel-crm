# VICIdial integration

## Ownership and configuration

The CRM talks to campaign-selected `VicidialServer` records through `VicidialServerRepository`. User VICIdial credentials and phone credentials belong to user telephony profiles; server API credentials/endpoints are separate settings. Do not substitute a global configured URL for a missing campaign server without inspecting the service's actual resolution rules.

[config/vicidial.php](../../config/vicidial.php) declares Agent/Non-Agent endpoint defaults, transport/connect timeouts and retry settings, SSL verification, report timezone, disposition mappings, polling, session behavior flags, webhook secrets, history-sync windows and supervisor thresholds. Database server fields can determine actual endpoint choice. `VicidialEndpointResolver` accepts a single server, prefers explicit endpoints, and derives sibling script paths when possible; it never searches an unrelated server when an endpoint is missing.

`CampaignVicidialMapping` and scope resolver/catalog services distinguish a CRM campaign from one or more VICIdial campaign codes on a chosen server. Reporting and synchronization must preserve that scope. They are not interchangeable strings. Ingroups are separately selected and managed phone groups, not entries in this campaign-mapping relation.

## Paths through the code

| Workflow | Main source |
|---|---|
| Agent actions, including `external_dial` | `VicidialProxyService` |
| Non-Agent requests/report/lead access | `VicidialNonAgentApiService` |
| Login/verify/pause/ingroups/local state/iframe | `VicidialSessionService`, `VicidialSessionController`, `resources/js/vicidial-session.js` |
| Call orchestration and state | `CallOrchestrationService`, `CallStateService` |
| Scoped reports/history | Scope resolvers, realtime/historical report services and providers, `VicidialCallHistorySyncService` |
| Push/Call URL ingress | `VicidialEventsWebhookController`, `VicidialCallUrlController`, `VicidialCallUrlService` |

Agent API transport uses GET requests with credentials and action parameters. Keep complete URLs out of logs/tickets. It applies bounded timeouts and retries only actions deemed safe; a timeout on an action can mean unknown remote outcome. Avoid blindly repeating a dial or transfer.

Current default session mode is iframe plus Agent API (`session_iframe_agent_api_only=true`). Optional Non-Agent live confirmation defaults false; local/development can skip that check. A verified local session may therefore reflect trust in iframe state, not a confirmed remote live_agents row. These toggles do not remove Non-Agent usage from reporting/leads. Outbound HTTP dialing normally requires a usable same-campaign local VICIdial session.

## Incoming events and campaign Call URLs

POST `/api/webhooks/vicidial-events` accepts user, event, message, lead_id and counter. `VICIDIAL_EVENTS_WEBHOOK_SECRET` is mandatory in every environment as `X-Webhook-Secret`; the comparison is constant-time. Empty configuration returns 503 and a missing or invalid header yields 401. The GET route only returns an acknowledgment saying POST only. Push is not the only synchronization path: active-lead polling remains available.

The five GET callbacks under `/api/webhooks/vicidial/` are start-call, dispo-call, no-agent-call, dead-call-trigger and pause-max. Each uses `throttle:vicidial` and query `sig` checked against the nonempty `VICIDIAL_CALL_URL_SECRET` (fallback to the events secret). Empty secrets are rejected in every environment. This is a shared-secret equality check, not a signed payload/HMAC. Query strings can be logged by infrastructure; protect/redact them. See [callback validation and response shapes](../developer/api-reference.md#webhooks-and-public-operations).

## Validation boundaries

Source and fixture tests cover normalization/failure paths, but live server permissions, supported API functions, iframe login, proxy TLS, report scripts, campaign membership and call completion were not validated in this review. The preserved [direct integration guide](../asterisk/VICIDIAL_DIRECT_CRM_INTEGRATION_GUIDE.md) and example PHP files describe an external integration approach; do not copy them over the application or assume current CRM routes match historical examples.

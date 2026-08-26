## 1. Campaign-Scoped Server Resolution

- [x] 1.1 Add failing repository coverage proving exact campaign selection, within-campaign default/priority ordering, and no cross-campaign fallback.
- [x] 1.2 Update `VicidialServerRepository` to return only an active server assigned to the requested campaign.
- [x] 1.3 Add failing Non-Agent API coverage proving each campaign uses the endpoint derived from its selected server even when the global override is set.
- [x] 1.4 Update `VicidialNonAgentApiService` endpoint precedence to preserve campaign-specific server routing.
- [x] 1.5 Apply campaign-specific Non-Agent endpoint precedence to credential synchronization and cover both server URLs.

## 2. Supervisor API Campaign Context

- [x] 2.1 Add failing feature coverage for campaign-scoped Supervisor sessions, calls, dispositions, totals, and non-sensitive routing context.
- [x] 2.2 Update `SupervisorAgentsController` to resolve the active CRM campaign, scope every telephony query, and return campaign/server context plus campaign-aware agent records.
- [x] 2.3 Add failing feature coverage proving Supervisor telephony actions pass the requested campaign to `VicidialProxyService` and unmapped campaigns cannot fall through to another server.
- [x] 2.4 Update `SupervisorTelephonyController` validation and routing so VICIdial-directed actions use explicit Supervisor campaign context.

## 3. Supervisor Interface

- [x] 3.1 Update Supervisor dashboard response handling to display the active campaign/server identity and an actionable unmapped-campaign state without exposing connection details.
- [x] 3.2 Send campaign context with monitor, whisper, pause, logout, and notification requests; disable unavailable/in-flight actions and report truthful success or failure feedback.
- [x] 3.3 Add or update render-level tests for the campaign/server context and control states.
- [x] 3.4 Add CRM campaign selection to Supervisor and use the mapped server's logged-in-agent feed without VICIdial campaign filtering.

## 4. Validation

- [x] 4.1 Run the focused PHPUnit tests for repository, VICIdial services, Supervisor APIs, and Supervisor dashboard rendering.
- [x] 4.2 Run Laravel Pint on changed PHP files and build the frontend assets.
- [ ] 4.3 Validate configured and unmapped campaign Supervisor flows with Playwright, including action feedback, responsive layouts, failed requests, and browser console health.
- [x] 4.4 Review security boundaries, verify no credentials or URLs are exposed, and reconcile implementation details with the OpenSpec artifacts.

# Extending the CRM

## Add a JSON endpoint

Choose the correct boundary first. Browser-session functionality belongs with `routes/web.php`; external bearer-token functionality belongs in an explicitly designed Sanctum route group, not merely under an `/api` URL. Add a named route and relevant auth, role, campaign, feature, and throttle middleware. Use a Form Request for nontrivial rules and explicit resource authorization. Keep domain logic in an existing service, preserve response conventions used by its browser consumer, and document any new error shape.

Inspect campaign scope and ownership in the service, not only the route. A selected campaign is not tenant membership. Test unauthenticated, forbidden, invalid-input, missing campaign, and failed-provider paths as well as success. Update the [endpoint inventory](endpoint-inventory.md) and [contracts](api-contracts.md).

## Add a form or capture field

Inspect `Form`, `FormField`, `AgentScreenField`, admin requests, visibility helpers, configured table allowlists, and `FormFieldsSchemaSyncService`. Storage is table-backed, so changing metadata can have schema implications. Handle nullable/hidden/unrepresented fields, conditional visibility, option values, sale amount semantics, and exports consistently. Check both full-page and quick-form/capture consumers. Never run schema synchronization against a live database as an exploratory step.

## Add telephony behavior

Use existing Agent API/Non-Agent API services, `VicidialEndpointResolver`, and campaign mapping resolvers. Do not fall back to unrelated servers when a scoped endpoint is absent. Define timeout, retry safety, partial failure, idempotence, and credentials redaction. Route call state changes through `CallStateService` and preserve terminal-state/race behavior. Keep SIP media, local HTTP session, and VICIdial agent session separate.

## Add a background job or event

Choose connection/queue intentionally, bound timeouts below effective queue visibility windows, design retry-safe execution, and emit events after successful local commits when appropriate. Current realtime broadcasts use deferred delivery; use a durable connection explicitly if durable work is required. Check scheduler registration, overlap/uniqueness locks, alerting, and worker coverage. Email jobs use a separate configured transport/queue policy.

## Add UI or authorization

Reuse the application's Blade components, Tailwind variables and Alpine lifecycle patterns. Apply Impeccable guidance within the existing design language, then verify with Playwright. Extend route/policy/feature layers together; do not rely on hiding buttons. Add broadcast channel authorization tests when exposing new private events. Update developer and operations documentation and synchronize the associated OpenSpec change.

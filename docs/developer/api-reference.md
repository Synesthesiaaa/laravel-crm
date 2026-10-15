# API reference

Reviewed against source on 2026-10-15. This is the current application contract, not a versioning or backwards-compatibility guarantee. External services, deployment credentials, live responses and telephony/media behavior were not exercised.

Use the [201-registration endpoint inventory](endpoint-inventory.md) for every explicit route, HTTP method, controller, inherited access boundary and route-local throttle/feature gate. Use the [contract reference](api-contracts.md) for exact API request rules, response branches and shared responders. Those references are complementary: validation alone does not establish authorization.

## Boundaries and error handling

| Family | Authentication and CSRF | Typical response |
|---|---|---|
| Browser `/api/...` declared in web.php | Session auth where listed; campaign group where listed; CSRF for POST/PUT/PATCH/DELETE except two webhook exceptions | JSON controller contract; send `Accept: application/json` |
| `/api/user`, `/api/v1/disposition-codes` | `auth:sanctum`, suitable Bearer token; API middleware, no web CSRF | User JSON / disposition resource collection |
| AMI and VICIdial events | Public POST, CSRF exempt; shared header secret when configured; production missing-secret rejection | Received/processed flags, not remote-action acknowledgment |
| VICIdial Call URLs | Public GET with required shared `sig`, throttled | `ok`, optional `data`; processing failure may still be HTTP 200 |
| Operations and health | Public at route layer | Operational JSON/configuration; no login requirement |
| Admin and forms | Session/campaign/roles/policies; CSRF for writes | HTML, redirect, JSON or download according to action |

Common framework JSON errors are 401 unauthenticated, 403 forbidden, 404 missing resource/campaign, 419 CSRF mismatch, 422 validation (`message` + `errors`), and 429 throttled. Controllers also use `{success:false,message}`, `{ok:false,error}`, and webhook-specific envelopes. Do not parse HTTP 200 as success without reading the relevant flag. HTML callers can receive redirects/session errors instead of JSON. No catch-all uniform JSON exception envelope is declared.

Named limiter definitions in `bootstrap/app.php`: `api` 60/minute, `telephony-poll` 240/minute, `vicidial` 30/minute, `form-submit` 10/minute (user ID or IP); `login` 5/minute/IP; `csv-import` 2/hour (definition alone does not attach it to a route). Explicit numeric routes include operations insights 60/minute, unsubscribe POST 10/minute, and SMTP/test-self 3/minute. Named buckets are shared across routes using the same limiter/key. Rate-limit 429 handling should back off rather than increase polling.

## Important browser contracts

All illustrative campaign codes/IDs below are placeholders and must refer to configured resources in an authorized sandbox. Requests shown are not executed examples.

### Dial, status, hangup, disposition

`POST /api/call/dial` requires `phone_number` (string maximum 50); optional `campaign` maximum 50, integer `lead_id`, `phone_code` maximum 5. The service rejects an existing active call, pending disposition, missing VICIdial credentials or unusable local agent session (unless explicitly configured otherwise). It creates a local session and invokes VICIdial `external_dial`. Success includes `success:true`, `session_id`, echoed `lead_id`/`phone_number`, `client_name:null`, and `lead_data:[]`; failure returns 422 with `success:false`, `message`, and sometimes `error`. A returned session ID is not proof of answered audio.

Example same-origin call from a signed-in page:

```javascript
const response = await fetch('/api/call/dial', {
  method: 'POST', credentials: 'same-origin',
  headers: {
    Accept: 'application/json', 'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
  },
  body: JSON.stringify({ campaign: 'example_campaign', phone_number: '15550100100' }),
});
const result = await response.json();
// Check response.ok and result.success before using result.session_id.
```

`GET /api/call/status` returns one of: active `call` with `session_id`, phone, status, lead and duration; inactive with `disposition_pending:true` and `pending_call`; or `{success:true,active:false,disposition_pending:false,call:null}`. `POST /api/call/hangup` accepts optional integer `session_id` and campaign string; source ownership/service checks still apply. Predictive dial accepts campaign and requires the `predictive_dialing` feature gate.

`POST /api/disposition/save` requires `campaign_code` and `disposition_code` strings maximum 50. Optional fields include integer lead/session IDs, phone maximum 30, label maximum 255, remarks/notes, nonnegative integer duration and lead JSON. Success is `{success:true}`; service failure is 422 `{success:false,message}`. Do not infer a lead/session ownership relationship just because both IDs validate.

### Session and telephony tools

`POST /api/vicidial/session/login` accepts optional campaign, phone/vd credentials maximum 32, boolean blended, ingroup array with string entries maximum 32. Verify/logout/status/local-status/iframe-url accept campaign. Pause requires `PAUSE|RESUME` (also lowercase); pause-code maximum 6; ingroups requires `CHANGE|ADD|REMOVE` (also lowercase), optional ingroups/blended. Session-control and ingroup-management feature middleware differ. These are CRM-local session operations plus integration workflows; the iframe/verify configuration can trust local iframe state rather than prove remote live_agents membership.

DTMF requires `digits` string maximum 64. Blind transfer requires phone maximum 50; warm transfer accepts phone/ingroup and optional boolean consultative; local transfer requires ingroup. Swap park requires target `CUSTOMER|XFER` (also lowercase). Other transfer/park commands use campaign context without additional typed body rules. Recording start accepts optional stage maximum 14; lookup accepts agent_user maximum 20, lead ID, Y-m-d date and extension maximum 100. Tool responses generally use `{success,message,data}`, 200 on success / 422 on service failure. Feature gates can reject before these actions.

Callback schedule requires integer lead ID and callback_datetime string maximum 32 (not a strict date rule here); optional type `ANYONE|USERONLY`, user maximum 20, comments maximum 255, status maximum 6. Remove/info require lead ID; info accepts search_location `CURRENT|ARCHIVE|ALL`. Lowercase enums are accepted and normalized.

### Leads, history and application data

Lead search/add require phone string maximum 32; field requires lead ID and field name maximum 100; switch requires lead ID; update-fields requires an array. Info/hydrate accept optional lead ID or phone. Additional service rules and provider availability determine results. Hydrate has different middleware from lead-tools endpoints; use the inventory instead of assuming one feature gate covers the entire family.

History accepts campaign, date bounds (Y-m-d), agent, phone, lead, status/disposition/VICIdial campaign, direction `INBOUND|OUTBOUND`, sort `called_at|agent|duration|status|vicidial_campaign`, dir `asc|desc`, positive page, and per_page `15|25|50|100`. Refresh POST queues synchronization; a queue acceptance/status response is not fresh remote data. Read responses include local data and synchronization metadata; consult CallHistoryController and LocalCallHistoryQueryService before relying on fields.

Attendance start requires code string maximum 30. Widget PUT requires widget route parameter `softphone|quick_form|workspace` and layout array; dimensions/coordinates/z-index have bounded numeric rules. Notification detail/read, search, capture, reporting and quick-form bootstrap have action-specific query/JSON shapes in the contract reference; do not assume all use the same envelope or pagination. `/api/client-errors` is presently an acknowledgment-only closure.

`GET /api/sip/credentials` returns SIP connection settings and the signed-in user's password when extension/password exist, or 422 with a setup message. Treat this response as sensitive: never cache/share it or print it in diagnostics.

## Sanctum v1 example

```http
GET /api/v1/disposition-codes?campaign=example_campaign HTTP/1.1
Authorization: Bearer <token-issued-out-of-band>
Accept: application/json
```

Required query `campaign` is checked for an empty string. Missing campaign returns 422 `{ "message": "Campaign is required" }`. A successful empty collection is `{ "success": true, "data": [] }`; populated entries contain `code`, `label`, `sort_order` through DispositionCodeResource. The route declares no token ability restriction or explicit route throttle. It does not inherit browser campaign middleware. No token issuing route is declared by this application.

## Webhooks and public operations

AMI accepts `event|Event`, correlation `linkedid|Linkedid|Uniqueid` and `channel|Channel`; missing event returns 200 `{received:true,processed:false,reason:"missing event"}`. Supported branches include hangup variants, Bridge/BridgeEnter and DialEnd. A known event without a matched call can remain unprocessed. VICIdial events require nonempty event/user for processing; additional inputs include message/lead_id/counter. Missing event/user returns a received-but-unprocessed acknowledgment. GET `/api/webhooks/vicidial-events` is only a liveness acknowledgment (`POST only`), not event processing.

Both POST webhooks require `X-Webhook-Secret` if a secret is configured; wrong/missing header gives 401. Empty secret configuration is accepted outside production but gives 503 in production. Their secrets are distinct config keys. Example body for a parser-compatible AMI event (not a call fixture):

```json
{"event":"Hangup","linkedid":"example-linked-id","channel":"SIP/example-00000001"}
```

Call URL callbacks require query `sig` equal to the nonempty configured shared secret using `hash_equals`; this is a shared secret comparison, not an HMAC over the payload. Start-call requires campaign and lead_id or phone_number. Dispo-call requires campaign, dispo, and call_id or lead_id. No-agent-call substitutes required status. Dead-call-trigger requires campaign/call_id and defaults dispo to DEAD. Pause-max requires campaign/user. Invalid signature gives 401 `{ok:false,error:"invalid_signature"}`; invalid query gives 422 `{ok:false,error:"validation_failed",fields:{...}}`. Processing failure may use HTTP 200 `{ok:false,error:"processing_failed",...}`. Never embed real callback sig values in docs/logs.

`GET /api/telephony/health` returns status/metrics/timestamp, with 503 only for critical status (degraded remains 200). `/api/websocket/health` reports enabled/host/port/scheme from configuration. `/api/operations-insights` and `/api/operations-insights/records` are public, rate-limited data surfaces. `/up` is Laravel's configured health route. Deployment network restrictions and live availability are unverified.

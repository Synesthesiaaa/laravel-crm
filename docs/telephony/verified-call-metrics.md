# Verified call metrics

The standardized rates are calculated by `VerifiedCallMetricsService` from normalized evidence. The previous ANA subtraction and non-system-disposition Contact Rate are superseded. Existing call control, raw VICIdial reports, call-history synchronization and form/sales statistics remain separate.

| Metric | Numerator | Denominator |
| --- | --- | --- |
| Answer Rate | Verified answered customer attempts, human or machine | Actual valid customer call attempts |
| Contact Rate | Unique dialer lead identities with verified live agent–customer conversation | Unique dialer lead identities attempted |
| Abandoned Rate | Live answers disconnected before agent connection | All verified live answers |

All percentages are numerator / denominator × 100, rounded once to two decimals. Complete empty cohorts return 0.00%. Missing coverage, missing essential evidence, unresolved classifications or pending pre-agent outcomes return N/A with a reason. Source-local lead identities include the server namespace; mirrored leads across servers are not claimed to represent unique people.

`valid_attempt=false` is reserved for rows that were not actual customer dial attempts, such as a rejected malformed originate request or an internal leg. Busy, unanswered, failed customer dials and disconnected-number attempts remain actual attempts. A non-attempt cannot carry positive answer/conversation evidence.

## Source verification and activation

Production credentials and actual VICIdial/Asterisk records were unavailable during implementation. **No deployment-specific disposition map or live source adapter has been verified or activated.** Existing raw statuses, `answered_at`, synthesized connections, bridge events and stale-session abandonment are not automatically promoted to metric evidence. Until an attested evidence feed supplies complete coverage, N/A is intentional.

1. Run the new migration using the normal deployment process. It creates `telephony_metric_attempts`, `telephony_metric_agents` and `telephony_metric_coverage`.
2. Inspect the authenticated source schema, source clocks, campaigns and deployed dialplan. Reconcile original customer-attempt identifiers, verified answer events, human/machine classification, agent participation, conversation evidence and terminal events against actual records.
3. Normalize those verified facts into the contract below. Callbacks/redials get distinct original attempt keys. Multiple log rows, duplicate events and transfer legs retain their original customer attempt key. Preserve participants separately. Do not use a phone number as a substitute for missing lead identity.
4. Import a reviewed backfill with `php artisan telephony:import-metric-evidence /secure/path/batch.json`, or configure `TELEPHONY_METRICS_WEBHOOK_SECRET` and POST normalized batches to `/api/webhooks/call-metric-evidence` with `X-Webhook-Secret` over HTTPS. This dedicated secret has no default and is separate from the raw AMI webhook secret.
5. Declare coverage only after the source feed proves **all** customer attempts in the stated source/campaign/time range have been included. Receiving a handful of events does not prove completeness. Feed corrections replace normalized snapshots with a newer source watermark.
6. Reconcile sampled source records and all three exported numerators/denominators before production acceptance. Include live conversations, machines, unanswered/busy/failed/disconnected dials, pre-agent disconnects, transfers, callbacks, duplicated logs and late events.

The endpoint is for a trusted server-side normalizer, not browser clients or unverified raw AMI payloads. It validates scope, types, timeline consistency, source watermarks and projection identity; it cannot independently authenticate a human/machine classification asserted by an upstream system. Store the webhook secret outside version control. The existing VICIdial historical sync is unchanged and does not claim to populate verified evidence.

## Batch contract

Each batch is a JSON object with:

- `crm_campaign`: configured CRM campaign code.
- `server_id`: existing `vicidial_servers.id` for that campaign's configured server.
- `campaign`: an exact, permitted VICIdial campaign code on that server.
- `definition_version`: `verified-v1`.
- `source_reference`: non-secret reference identifying the source query, event export or evidence bundle.
- `observed_at`: ISO 8601 source watermark with an explicit UTC offset or `Z`. Required when coverage is absent; otherwise defaults to `coverage.observed_through`. Future source clocks beyond one minute are rejected.
- `attempts`: an array, possibly empty, of at most 5,000 **complete normalized snapshots**. Do not send individual partial events as snapshots.
- Optional `coverage`: `{ "start": "...", "end": "...", "observed_through": "...", "metrics": ["answer_rate", "contact_rate", "abandoned_rate"] }`. Times use explicit offsets. `end` is exclusive. `observed_through` must reach the whole covered range; the batch watermark must reach it as well. Coverage is an explicit completeness attestation, not a rate input inferred from rows.

An attempt snapshot contains all these fields:

| Field | Meaning |
| --- | --- |
| `attempt_key` | Stable original customer-attempt identity; max 100 characters. Must already reconcile transfer/log aliases. |
| `lead_key` | Stable source-local lead identity, or null when unknown. |
| `started_at` | Verified customer attempt start, with explicit offset. |
| `answered_at` | Verified customer-leg answer timestamp, or null. |
| `agent_connected_at` | Verified agent connection timestamp, or null. |
| `ended_at` | Terminal customer-attempt timestamp, or null. |
| `valid_attempt` | Actual customer attempt: true/false/null for unknown. |
| `answered` | Verified human or machine answer: true/false/null. |
| `live_answer` | Verified live-human answer: true/false/null. Machine answers are false. |
| `agent_connected` | Verified connection state: true/false/null. Absence of a generic bridge event alone does not establish false. |
| `conversation_verified` | Verified live conversation: true/false/null. Positive evidence requires live answer, agent connection, positive elapsed time and a verified participant. |
| `outcome` | Source-normalized audit label, e.g. `unknown`, `machine`, `busy`, `unanswered`, `disconnected_number`, `conversation`, `pre_agent_disconnect`, `transfer`. Labels never drive the calculation. |
| `agents` | Participant snapshots: `[{ "login": "source-agent-login", "conversation_verified": true }]`. Empty when no participant is known. |

Flags and timestamps must agree. Event times must follow the customer-attempt timeline and not exceed the source watermark. Null means unknown, not false. A lead with repeated attempts counts once for Contact Rate; an answering machine never establishes a conversation. An active live answer without a known connection/final outcome keeps Abandoned Rate unavailable.

Source snapshots are ordered by `observed_at`. Older deliveries cannot overwrite newer facts. Conflicting snapshots at the same watermark, changed original campaign/start/known lead identity, duplicate attempt keys within a batch and duplicate agent logins are rejected. Whole batches are transactional. Evidence hashes and activity-log batch hashes retain provenance; source references and watermarks are returned with metric results.

On MySQL, attempt/lead/agent identity columns use binary collation. Padded or empty identities are rejected. The database enforces projection uniqueness by server, CRM campaign and original attempt key. Overall counters, campaign counters and coverage are read in one transaction; standalone MySQL reads use next-transaction REPEATABLE READ without changing the session default. A caller wrapping this service in its own transaction must provide a consistent snapshot isolation level.

## Scope, freshness and filtering

- Report date ranges use local calendar boundaries in the requested timezone, converted to half-open UTC ranges. All three rates select the same attempt-start cohort. Later lifecycle evidence updates its original attempt's cohort; outcomes are as of the disclosed source watermark.
- Coverage intervals can overlap or join contiguously. Gaps or any missing mapped campaign make the affected overall metric unavailable. Campaign rates are calculated from raw counters, never averaged.
- Live/Today requests disclose their effective source end. A feed may lag the requested end by at most 60 seconds; older coverage yields `stale_source_coverage` and N/A. Source health remains separate from the health of legacy raw report APIs.
- Agent filters match verified participants. Contact success is specific to that participant. Pre-agent abandoned calls cannot be assigned to an agent, so agent-filtered Abandoned Rate is N/A with `agent_attribution_unavailable`.
- Standardized rates ignore raw disposition inclusion/exclusion filters. These are lifecycle/identity metrics, not status mixtures. Unsupported in-group filters return `ingroup_evidence_unavailable` rather than silently broadening the cohort.
- The dashboard follows its selected sales time window for call-metric cards but does not use form submissions as call evidence. Form activity and sales remain separate measures.
- Ingests publish an after-commit update on the existing private dashboard refresh channel without invalidating form/sales caches. Supervisor and Live/Today report polling read the same metric service.

## API and exports

- `GET /api/reports/verified-metrics`
- `GET /api/reports/verified-metrics/export`

Both use existing authenticated Team Leader/Admin/Super Admin access and CRM campaign resolution. Filters: `campaign`, permitted `campaigns`, `agent`, `timezone`, `query_date`, `end_date`, and `mode=historical|today|live`. No source-table access or credentials are exposed. Existing historical and realtime reports also include the shared result under `call_metrics`; supervisor stats include `stats.call_metrics`.

Each metric has `rate`, `numerator`, `denominator`, `state`, `reason` and `definition`. Results include scope, definition version, source coverage and source references. Raw VICIdial diagnostic totals are retained and labeled separately; they are not substituted for verified rate inputs.

CSV uses the same result and includes counts, formula, availability, UTC bounds, timezone, agent, source watermarks, source references and scope type. Numeric campaign codes retain their original label. `scope_type` distinguishes the overall result from a campaign literally named `overall`. Spreadsheet formula prefixes in string cells are escaped.

## Verification performed

The implementation was exercised with isolated normalized fixtures and existing regression tests, plus desktop/mobile rendering. The local MySQL migration and aggregate SQL were checked separately. These checks validate implementation behavior; **they do not constitute reconciliation against production VICIdial records**.

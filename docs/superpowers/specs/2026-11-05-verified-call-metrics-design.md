# Verified call metrics: investigation and implementation design

Status: evidence-backed calculations, ingestion, UI/API/export integration and automated tests implemented. Production source investigation/reconciliation remains incomplete; no production accuracy claim. This request supersedes the earlier ANA subtraction and non-system-disposition contact formulas. Deployment and evidence-feed requirements are documented in ../../telephony/verified-call-metrics.md.

## Required definitions

- Contact Rate = distinct successfully contacted leads / distinct attempted leads * 100. Successful contact requires verified live customer conversation with an agent.
- Answer Rate = answered valid call attempts / valid call attempts * 100. Human and machine answers are included when the verified customer-leg answer definition permits this. Do not subtract abandoned rate.
- Abandoned Rate = live answered calls disconnected before agent connection / live answered calls * 100. ANA alone does not establish this lifecycle.
- Percentages use two decimals. A complete, confirmed empty cohort may return zero; unavailable or unverified evidence returns N/A with a reason.

## Observed local evidence, 2026-11-05

Read-only queries against the connected development MySQL database returned:

| Source | Rows |
| --- | ---: |
| call_sessions | 0 |
| telephony_call_histories | 0 |
| campaign_disposition_records | 0 |
| campaign_vicidial_mappings | 0 |
| vicidial_call_history_sync_states | 0 |
| unmatched_ami_events | 0 |
| vicidial_servers, excluding deleted rows | 0 |
| users with a VICIdial login | 0 |

The local disposition table contains generic labels such as NA / No Answer, BUSY / Busy, NAN / Not a Number and SALE / Sale. These are not verified production campaign definitions and cannot establish event-level outcomes.

The user identified dial.cidglobal.ph and dial-3.cidglobal.ph as the intended servers. Both accept TCP connections on HTTPS port 443 and MySQL port 3306 from this environment. TCP reachability does not verify database authentication, schema, account permissions or event semantics. The user confirmed credentials are stored in production and are absent from development. No authenticated remote record inspection has occurred.

## Architecture findings

- HistoricalTelephonyReportService consumes aggregate call_status_stats and call_dispo_report responses. Those exports cannot establish distinct attempted/contacted lead counts or customer-answer-to-agent-connect lifecycles by themselves. Its current ANA-based subtraction is incompatible with the new definitions.
- RealtimeTelephonyReportService and SupervisorOperationalService independently calculate answer metrics. Their rolling cohorts can include calls based on different lifecycle timestamps, while Today and historical paths use other boundaries. Centralize the cohort definition.
- CallStateService can synthesize answered_at when assume_connected is set. DispositionService and the VICIdial event controller use this path. An answered_at value is therefore not sufficient provenance for verified answer metrics.
- AmiWebhookController and AmiListenerCommand both transition calls to connected for DialEnd ANSWER and bridge events. CallUuidMappingService also supports extension-based fallback correlation. Neither an arbitrary bridge nor fallback correlation proves a live customer conversation.
- VicidialHistoricalCallProvider reads vicidial_log and vicidial_closer_log. HistoricalCallRecord has stable source identity and lead fields, but the provider currently returns talkSeconds as null and does not retain independent answer, human detection or agent-connection evidence.
- TelephonyCallHistory scopes source identities by server, CRM campaign and source table. That storage uniqueness prevents duplicate synchronization rows, but does not alone deduplicate transferred legs across source tables.
- ReportingController validates historical campaign/date/timezone/disposition filters but has no historical agent filter. Live validation currently accepts mode only. Standardized filters need an explicit shared contract.
- No dedicated telephony metric CSV/PDF export endpoint was found in ReportsController, ReportingController or report views. Raw VICIdial proxy responses are not standardized metric exports. Any new export must consume the same normalized metric result as the UI.

## Recommended implementation

Extend the existing ingestion/reporting services with a canonical, auditable call-attempt projection and one backend metric aggregation service. Preserve operational call control and CRM form/sales calculations.

An aggregate-disposition-only implementation is unsuitable because it loses lead and lifecycle identity. Browser-side computation would also create inconsistent APIs and exports. Existing aggregate exports may remain as diagnostic/raw report sections, clearly separated from verified metrics.

### Evidence and identity

- Retain verified source server, original attempt identity, original customer channel, lead identity, campaign, agent participation, event timestamp, event/source reference and classification provenance.
- Correlate original attempts and transfer legs using the actual deployed schema and dialplan. Do not assume linkedid, source-table row identity or a phone number alone uniquely identifies a customer attempt or lead.
- Preserve repeated attempts and callbacks as separate call attempts, while deduplicating leads for Contact Rate. Server-local lead IDs require server identity; verify whether leads are mirrored between these servers before claiming organization-wide unique-person counts.
- Store customer answer, verified live-human detection, agent connection, conversation evidence and terminal disconnect independently. Machine answers may increase Answer Rate but never Contact Rate or the live-answer abandonment denominator.
- Do not reinterpret NA, AB, DROP, BUSY, ADC, ANA or other status codes without deployed status definitions and corroborating lifecycle records. Do not treat stale-session reconciliation as predictive-dialer abandonment.
- Reject contradictory timelines or conflicting evidence as unverified. Keep unknown, failed, busy, disconnected-number, unanswered and machine outcomes distinguishable without pretending all are provable from existing exports.

### Shared filters and aggregation

- Resolve authorized CRM campaign and mapped server/campaign scope once. Apply campaign, agent, direction and time filters consistently before aggregation. Agent-filtered rates must disclose how pre-agent abandoned calls are attributed rather than silently assigning them to a user.
- Use one attempt-start cohort with half-open UTC storage boundaries derived from the requested reporting timezone. Use the same cohort for numerator and denominator; later events update the original attempt. Verify the deployed database timezone and source timestamp semantics first.
- Group/deduplicate in SQL using indexed source/campaign/start/lead/attempt columns. Compute per-attempt lifecycle flags and per-lead contact flags before the final counts. Never average per-campaign percentages.
- Return each metric with numerator, denominator, rate, definition, source coverage, freshness, timezone and reason for N/A. Unknown classifications must not be silently excluded from a required denominator.
- Refresh from existing event/broadcast and sync paths. Publish data watermarks; distinguish a current event projection from lagging historical synchronization. Do not label stale data real time.
- Route historical summaries, comparisons, campaign rows, live/today monitoring, dashboard KPIs and metric exports through this service. Display formula tooltips and source coverage; prevent fallback to the superseded formulas.

## Verification required before implementation acceptance

Obtain a secure read-only connection or sanitized source export for each server, including deployed schema/version, campaign mappings, status definitions, relevant call logs and customer/agent lifecycle evidence. Candidate tables to inspect when present include vicidial_log, vicidial_closer_log, vicidial_agent_log, vicidial_auto_calls, vicidial_statuses, vicidial_campaign_statuses, queue_log, CDR/CEL and AMD-related data. Their existence and semantics have not been assumed.

Reconcile sampled original attempts covering live agent conversations, machine answers, busy, no answer, failed/invalid numbers, true pre-agent disconnects, repeated attempts, callbacks and transfers. Preserve source references and only aggregate non-sensitive evidence in audit artifacts.

Automated tests must cover normal cohorts, distinct leads versus attempts, duplicate events, cross-table transfers, server ID collisions, missing lead identity, agent filters, timezone/DST/date boundaries, late/out-of-order events, unknown/contradictory evidence, zero denominators, stale or partial coverage, campaign authorization and parity of UI/API/export results. Existing call-control, synchronization and dashboard tests must remain green.

## References

- Asterisk DialEnd: https://docs.asterisk.org/Latest_API/API_Documentation/AMI_Events/DialEnd/
- Asterisk AgentConnect: https://docs.asterisk.org/Latest_API/API_Documentation/AMI_Events/AgentConnect/
- Asterisk AMD: https://docs.asterisk.org/Latest_API/API_Documentation/Dialplan_Applications/AMD/

These describe event capabilities, not the verified configuration or behavior of the two deployment servers.

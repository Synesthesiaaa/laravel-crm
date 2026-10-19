## Context

Laravel 12/PHP 8.2+, Blade/Alpine/Tailwind 4, Super Admin role middleware, Spatie audit and database/Redis queues already exist. LeadHopper is the local recipient source; there is no standalone contact model. Target builds supplied by the operator: GoIP4/16 GST1610-1.01-70-17-8 and GoIP32 GST1610-1.01-70-16. Hardware transport behavior remains unverified.

## Goals / Non-Goals

Provide auditable campaigns, CSV and CRM lead intake, consent evidence, final audience review, scheduling, pause/resume/cancel, templates, history, reports, secure gateway/channel settings, asynchronous sending and safe recovery. Do not change telephony, guess channels, fabricate delivery, bypass carrier limits or send SMS during tests.

## Decisions

Use eight focused SMS tables. Message rows double as campaign recipients and retain excluded import results; a separate consent table preserves evidence for CRM lead reuse. Credentials use Laravel encrypted casts. Message bodies are encrypted. Query scopes and database indexes bound list and worker memory.

Audience previews use correlated existence checks in one streaming query rather than per-recipient consent lookups. Confirmation uses bulk state updates; each submission still revalidates consent immediately before its reservation. CSV intake first validates a bounded complete stream without database locks, then uses short row transactions. Unexpected import interruptions leave inspectable draft decisions, never an automatically approved audience.

Use a dedicated database queue with ID-only jobs. The scheduler repairs lost queued work. Short transactions serialize each campaign/channel claim; gateway I/O happens after commit. One active submission per gateway is deliberately conservative until hardware concurrency is verified. A persisted processing record survives worker death and becomes unknown, never queued again. Automatic retry is limited to work that has not crossed the submission boundary. Cancellation and opt-outs are checked immediately before reservation. UI confirmation binds a versioned audience digest.

Implement the documented-by-reference legacy HTTP send.html profile using fixed paths and explicitly allowlisted IPv4 destinations, no redirects/retries/proxies, bounded responses and sanitized outcomes. Success means submitted, never delivered. Unknown/malformed/timeouts are unknown. Live enablement requires both server opt-in and an operator firmware verification record.

Inbound UDP supports only the reference req/RECEIVE profile after separate operator verification, exact source/client ID/password authentication, size limits, durable deduplication and commit-before-ACK. Unsupported delivery events are rejected rather than guessed. STOP suppresses queued marketing. HTTP credentials and UDP cleartext require a trusted isolated network.

History phone filters use POST/session state and are excluded from request audit queries. Emergency stop leaves authenticated inbound STOP processing active. Explicit retention removes terminal content only; deduplication and state records persist. Schedules are interpreted in Manila and stored consistently with the application's datetime convention. Pending work expires three days after its scheduled/confirmed window, rather than three days after creating a long-scheduled draft.

## UI Direction

Operate extension of the existing Framed sheet: existing shell, tokens, neutral buttons, semantic tables, inline forms, task tabs, readable labels and both themes. The signature detail is a recipient eligibility ledger adjacent to an explicit Submitted to Gateway versus Delivered distinction. No invented metrics or standalone visual system.

## Risks / Trade-offs

No verified delivery-report mapping: confirmed delivery remains zero. Exact allowlisting excludes DNS and IPv6 initially. Serial gateway claims trade throughput for safety. UDP reference event IDs can wrap; duplicate IDs with changed content are quarantined until the operator resolves them. No retention deletion runs without explicit retention opt-in.

## Migration Plan

Additive migrations only. Deploy with SMS_LIVE_ENABLED=false; migrate, start dedicated worker and scheduler, configure allowlist and channels, validate firmware and separate receiver profile, then run approved hardware acceptance. Disable SMS and stop its processes before rollback; preserve/export SMS records before dropping tables.

## Validation

Fixture and HTTP fake tests; authorization on all routes; encrypted persistence; normalization, GSM segment estimates, consent/STOP, transitions, stale confirmations, rate limits, interrupted/ambiguous sends, spoofed UDP and duplicate events. Browser checks at 375/768/1024/1440 plus both themes; hardware acceptance recorded separately.

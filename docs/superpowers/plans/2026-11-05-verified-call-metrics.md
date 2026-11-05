# Verified call metrics implementation plan

Spec: ../specs/2026-11-05-verified-call-metrics-design.md

The user requested implementation after reviewing the source-access limitation. Implement evidence-backed calculations now; never claim production reconciliation without authenticated records. Work in the provided checkout and preserve form/sales behavior.

- [x] Add failing tests for standardized rates, identities, coverage, missing evidence, filters, timestamps and import idempotency.
- [x] Add indexed normalized attempt evidence, agent participation and coverage storage, plus validated transactional import/webhook ingestion. No default disposition mappings.
- [x] Implement SQL aggregation with distinct lead identities, attempt counts, shared definitions, provenance, freshness and per-metric availability.
- [x] Wire historical and live reports, comparisons, campaign rows, supervisor monitoring, dashboard and CSV export to the shared service. Retain raw dialer exports separately.
- [x] Update UI labels/tooltips and old-formula tests; run affected suites, build, Blade compilation, formatting and review.

Rulings: an agent filter cannot attribute pre-agent abandoned calls; that metric is N/A for agent-filtered requests. Standardized metrics do not inherit raw disposition filters. Unsupported in-group filters return N/A rather than broader rates. Attempt cohorts use UTC half-open boundaries derived from the selected timezone. Evidence ingestion is trusted server/operator input with explicit source references and completeness attestations; it cannot itself validate a deployment's dialplan. Imported lead identities are server-local and namespaced by server.

## Verification results

- 130 affected PHPUnit tests passed with 839 assertions.
- Production frontend build, Blade compilation, Pint and diff whitespace checks passed.
- Isolated desktop/mobile browser fixtures displayed Answer 50.00%, Contact 20.00%, Abandoned 50.00%, matching 3/6 attempts, 1/5 leads and 1/2 live answers; no captured JavaScript errors or document overflow.
- New tables migrated only after verifying the configured database target was local. MySQL aggregate SQL and the repeatable-read transaction path were exercised without inserting call evidence.
- Independent review found numeric campaign-code export reindexing; fixed and regression tested, including a campaign named overall. Follow-up review found no important remaining issue.
- Production records and source-specific normalization remain unverified; deployment activation requires an attested feed as documented in docs/telephony/verified-call-metrics.md. No default status classifications were added.

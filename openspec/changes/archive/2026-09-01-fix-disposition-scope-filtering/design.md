## Context

The historical Reports page has a disposition-scope selector with `all`, `exclude_system`, and `system_only` values. The backend already filters the disposition Pareto/table data using configured system codes, but the selector has no change handler and the call-status parser still exposes every status from VICIdial's status breakdown. This makes the scope appear ineffective and leaves related status summaries inconsistent with the disposition panel.

## Goals / Non-Goals

**Goals:**

- Refresh the historical dashboard when the disposition scope changes.
- Apply the scope to every disposition-derived output: Pareto, disposition rows/totals, funnel, status totals, and campaign top status.
- Keep total calls, answered calls, and hourly volume sourced from the authoritative call-status totals rather than changing their call-count denominator.
- Reuse the configured normalized system-code set in all parsers.

**Non-Goals:**

- Inferring system codes that are not configured in `VICI_REPORT_SYSTEM_DISPOSITION_CODES`.
- Changing VICIdial status/disposition classification or CRM persistence behavior.
- Redesigning the Reports page or changing the existing visual language.

## Decisions

1. **Refresh on scope selection.** Add the existing `refreshAll()` action to the disposition selector's change event. This keeps the API as the source of truth and makes the filter responsive without duplicating report aggregation in JavaScript.

2. **Filter status breakdowns at parse time.** Pass the selected scope into `parseCallStatus`, filter status-pair counts and per-campaign top-status calculations using the same configured normalized system-code set as `parseDispositions`, and leave raw call totals/hourly pairs unchanged.

3. **Preserve truthful empty states.** If the upstream status breakdown contains data but no values match the selected scope, return an empty/confirmed-zero status breakdown rather than falling back to all statuses. If no status breakdown exists, retain the existing unsupported state.

4. **Test each scope explicitly.** Extend unit coverage for all, exclude-system, and system-only selections and assert both disposition and status outputs. Add a view/feature assertion for the refresh binding where practical.

## Risks / Trade-offs

- [System code configuration is empty or incomplete] → The UI already displays the configured-code state; the filter will intentionally have no classification effect until deployment configuration identifies system codes.
- [Status counts and disposition counts use different upstream totals] → Only disposition-derived status fields are filtered; authoritative call totals and call-volume metrics remain unchanged and independently traceable.

## Migration Plan

No migration is required. Deploy the parser, view, and tests together. Configure `VICI_REPORT_SYSTEM_DISPOSITION_CODES` per VICIdial deployment when system-only filtering is needed, then clear Laravel configuration cache if configuration is cached.

## Open Questions

None.

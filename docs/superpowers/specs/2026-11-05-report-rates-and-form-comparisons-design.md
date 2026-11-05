# Report rates and dashboard form comparisons

## Agreed behavior

- Abandoned calls are VICIdial disposition `ANA` (Agent Not Available).
- Abandoned Rate = ANA calls / total calls * 100.
- Answer Rate = (answered calls / total calls * 100) - Abandoned Rate. For 100 calls, 60 answered and 10 ANA, display 50% Answer Rate and 10% Abandoned Rate.
- Contact Rate = sum of every non-system disposition count / total calls * 100. Use the existing configured system disposition list; no contacted-code configuration is required.
- Show Abandoned Rate beside Contact Rate in Reports.
- Dashboard Daily Comparison, daily activity, weekly activity and monthly activity must provide each form separately and an overall view.

## Approach

Extend the existing Laravel report and dashboard aggregation services and Blade charts. This reuses campaign access restrictions, report filters, source availability handling, sales rules, chart rendering and caching.

A separate reporting service or new chart library would duplicate these responsibilities and add unnecessary maintenance. Calculating rates only in the browser would leave API consumers and report comparisons inconsistent. The existing-service approach is recommended.

## Implementation decisions

- Apply historical rate calculations consistently to headline metrics, campaign rows and period comparisons. Clearly label the answer-rate adjustment in the interface.
- Use the same selected campaign, dates and disposition scope for each rate's numerator and denominator. A scope that excludes ANA will not count those excluded calls as abandoned.
- Round calculated percentages to two decimal places, subtracting before rounding. Confirmed zero totals yield zero rates. Missing or unverifiable source data stays unavailable; do not substitute zero for a failed report.
- Aggregate all non-system disposition columns for contact counts, replacing the historical contacted-group dependency. Keep qualified and successful group configuration intact.
- Provide an Overall / individual-form selector for Daily Comparison, retaining current-versus-previous-month lines and the existing volume/amount modes. Include the selected form in its accessible data table.
- For activity charts, add a named series for each form alongside the overall series, with matching time buckets and chart legend controls.
- Preserve existing date bases: Daily Comparison follows configured qualifying sales and `created_at`; daily activity uses rolling 24-hour `created_at` buckets; weekly and monthly activity use form `date` buckets.
- Include configured forms with zero activity. Keep queries restricted to allowed campaign form tables and prevent shared tables from being counted twice in overall totals.
- For custom rules sharing a table, preserve each rule's form identity. Overall counts a shared record once; the first qualifying configured rule supplies its overall amount.
- Update affected cache keys/invalidation as necessary so old combined-only payloads cannot mask the new form data.

## Validation

Add regression tests for the 100/60/10 example, mixed non-system dispositions, configured system exclusions, campaign-weighted totals, zero totals, missing sources and report scope filters. Test per-form daily comparisons and activity buckets against overall totals, including zero-activity forms and period boundaries. Run affected report/dashboard tests, PHP formatting checks and frontend build checks. Review rendered chart controls and labels where a local browser session is available.

## Scope

Use existing Laravel, Blade and ApexCharts infrastructure. No database migration or new dependency is expected. Live operational answer metrics remain tied to their existing source contracts unless the same verified ANA totals are available for their exact reporting window.

## Why

The deployed MBSales historical report showed 1,116 calls for November 25, 2026, while the unfiltered VICIdial call-status response through the CRM returned 6,381. Both counted 55 answered calls. Historical overrides explicit disposition filters, Today uses a different calculation, and the UI can present classification proxies as customer outcomes.

## What Changes

- Keep full VICIdial attempt, answer, and hourly counts independent of the disposition filter. Show the matched subtotal explicitly.
- Run Today and Historical through the same analytical calculation with a report-timezone date and honest source and period metadata.
- Use VICIdial's reported answered count for Answer Rate. Present Contact Rate and Abandoned Rate as unavailable until supported by agreed source evidence.
- Provide an offline reconciliation command, documented metric definitions, and a campaign-level rollout status. Require independent native exports and Operations approval before official use.

## Capabilities

### Modified Capabilities

- `telephony-operations-and-reporting` covers report scope, metric definitions, snapshot metadata, and reconciliation.

## Impact

The Reports API and Blade screen, targeted Laravel tests, and reporting documentation change. The work uses existing VICIdial transport and campaign mapping. It requires no migration or dialer writes. Native export acquisition and production sign-off require deployment access and Operations participation.

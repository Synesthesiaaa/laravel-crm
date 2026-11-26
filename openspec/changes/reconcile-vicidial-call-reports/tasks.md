## 1. Reporting contract

- [x] 1.1 Add regressions for the MBSales count discrepancy, explicit filter overrides, and invalid or incomplete totals.
- [x] 1.2 Keep full attempts, answers, and hourly volume independent of dispositions. Remove unsupported rate proxies.
- [x] 1.3 Reuse Historical for Today. Add report timezone, fetch-time, period, and campaign approval metadata.

## 2. Reports interface

- [x] 2.1 Update the existing report panel, control labels, filter subtotal, and source and freshness text. Avoid cross-scope stale responses.
- [x] 2.2 Check filtered totals, mode changes, provisional status, keyboard controls, and responsive layout in Playwright.

## 3. Reconciliation and release

- [x] 3.1 Add a read-only native-export reconciliation command, tests, and an Operations runbook.
- [x] 3.2 Run focused regression checks and sync the specification with the implemented behavior.
- [ ] 3.3 Obtain equivalent native reports for the release matrix and record Operations sign-off before approving any campaign or archiving.

## Verification

- The focused PHP suite passed 49 tests and 544 assertions. The suite covers the reported 6,381 full attempts and 1,116 filtered dispositions, timezone boundaries, unsupported outcomes, Live behavior, and native reconciliation results.
- Playwright used an isolated database and synthetic VICIdial responses. It verified report controls and layout at 375, 768, 1024, and 1440 px and checked that a superseded filter request could not replace current figures.
- Vite built successfully. Pint, the Impeccable detector, and strict validation of the OpenSpec change and main specification passed.
- Production reconciliation remains open. It requires independent native exports, confirmation of the deployed metric definitions, and Operations sign-off.

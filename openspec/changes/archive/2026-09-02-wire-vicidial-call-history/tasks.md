## 1. Historical data contract

- [x] 1.1 Add typed historical call record/result objects for normalized rows, pagination, filter options, and source health.
- [x] 1.2 Implement a read-only per-server VICIdial historical provider using `vicidial_log` and `vicidial_closer_log`, with scoped filtering, stable ordering, pagination, metadata, and safe failure logging.
- [x] 1.3 Add date/epoch normalization, phone search variants, direction, duration/wait-time semantics, and raw status/end-reason preservation without fabricated values.

## 2. Laravel integration and authorization

- [x] 2.1 Extend the call-history service to resolve the CRM campaign scope, narrow mapped VICIdial campaigns, map dispositions, and associate users by `vici_user` including soft-deleted users.
- [x] 2.2 Add an authenticated call-history API/resource with validation, role-appropriate agent scope, pagination metadata, effective filters, and source-health states.
- [x] 2.3 Update agent and supervisor/admin controllers to use authoritative historical results while preserving CRM submission history and live call-session behavior.
- [x] 2.4 Register the named API route and verify route/middleware authorization boundaries.

## 3. Call History interface

- [x] 3.1 Update the agent Call History filters and table for historical date/time, agent, phone, status, disposition, duration, campaign, and direction/details.
- [x] 3.2 Update the supervisor/admin Call History tab with the same historical fields while preserving the Submitted Records tab.
- [x] 3.3 Add accessible loaded, confirmed-empty, unavailable/retry, responsive-table, and keyboard-accessible detail states using existing design tokens/components.

## 4. Automated verification

- [x] 4.1 Add provider/service tests for source-table normalization, campaign isolation, user mapping, unknown agents/statuses, date/duration semantics, filters, sorting, and pagination.
- [x] 4.2 Update feature tests for agent/admin Call History and API behavior, including empty and remote-failure states and preserving live session coverage.
- [x] 4.3 Run focused PHPUnit tests, Pint, and relevant Laravel route/config checks; resolve failures.
- [x] 4.4 Run the frontend build/lint commands that exist in the repository and perform Playwright checks at representative desktop/mobile viewports, recording any live-source limitation.

## 5. Specification closeout

- [x] 5.1 Synchronize the OpenSpec artifacts with the implemented behavior and validation results.
- [x] 5.2 Archive the completed OpenSpec change after all implementation and verification tasks pass.

## 1. Regression coverage

- [x] 1.1 Add failing dashboard-configuration feature tests for campaign selection without session mutation, campaign isolation, authorization, atomic validation failure, custom-mode saving, and explicit legacy reset.
- [x] 1.2 Add failing sales-service tests for normalized accepted values, OR conditions, row de-duplication, optional amounts, count-only forms, multiple campaigns, stale references, and legacy fallback.
- [x] 1.3 Add failing dashboard and campaign-report feature tests proving every sales-derived section uses the same campaign attribution mode while activity totals remain submission-based.
- [x] 1.4 Add failing render or JavaScript tests for nested rule input names, old-input restoration, inline errors, warnings, campaign switching, rule add/remove controls, and the compact summary.

## 2. Campaign dashboard configuration

- [x] 2.1 Extend dashboard configuration normalization and persistence to preserve sections plus explicit custom sales form groups in the existing JSON document.
- [x] 2.2 Implement a campaign sales-rule service that safely resolves active forms, supported tag fields, optional numeric amount fields, physical columns, legacy mode, and stale-reference warnings.
- [x] 2.3 Extend the admin form request with bounded nested-array validation, allowed keys, campaign ownership checks, field-type checks, and clear indexed error messages.
- [x] 2.4 Update admin dashboard controller and routes to load a query-selected configuration campaign without mutating the active campaign session and to save or reset configuration atomically.
- [x] 2.5 Preserve campaign-scoped dashboard update broadcasting and invalidate any affected campaign aggregation caches after save or reset.

## 3. Sales aggregation

- [x] 3.1 Implement custom tag evaluation with trimmed case-insensitive exact matching, OR semantics, one count per row, and one optional amount contribution per row.
- [x] 3.2 Update selected-range Sales, Top Agent, leaderboard, and per-form breakdown aggregation to use custom rules when configured and existing marked fields otherwise.
- [x] 3.3 Update daily and month-to-date campaign report aggregation to use the same custom or legacy attribution rules.
- [x] 3.4 Ensure invalid rules, missing columns, malformed amounts, empty results, exclusive range boundaries, and disposition-only data return safe deterministic results.

## 4. Dashboard customization experience

- [x] 4.1 Add the approved campaign selector, stacked section editor, and responsive per-form sales-rule builder to the admin dashboard using existing components and design tokens.
- [x] 4.2 Add accessible condition/value controls, optional amount selection, add/remove actions, explicit legacy reset confirmation, focus states, inline errors, and old-input restoration.
- [x] 4.3 Add configuration warnings and a compact campaign summary that clearly identifies custom versus Field Logic mode without issuing expensive live aggregate queries.
- [x] 4.4 Update user-facing sales-derived labels, breakdowns, report empty states, and mode explanations where needed while preserving the existing dashboard design language.

## 5. Validation

- [x] 5.1 Run the focused PHPUnit configuration, aggregation, report, authorization, and rendering tests.
- [x] 5.2 Run relevant JavaScript tests and the configured frontend production build.
- [x] 5.3 Run `vendor/bin/pint --dirty --format agent` and review the final diff for security, query bounds, accessibility, and unrelated changes.
- [x] 5.4 Use Playwright to verify campaign switching, rule editing, validation failure, save/reset, and reflected user dashboard results at 375, 768, 1024, and 1440 pixels, including console and failed-network checks.

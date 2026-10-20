# Validation record - 2026-10-20

## Result

The shared redesign is implemented across the existing frontend. The complete application is **not certified for production or live telephony** by this UI task.

## Automated checks

| Check | Result |
| --- | --- |
| `npm run build` | Passed. Vite reports the existing ApexCharts chunk above 500 kB; the chart dependency remains dynamically imported. |
| Targeted Laravel regression run | 141 passed, 953 assertions. Covers shared UI, view lifecycle, login, dashboards, forms/submission, field logic, agent configuration/access, phone widgets, reports, email and branding. |
| Full Laravel suite with 512 MB PHP memory limit | 736 tests, 3984 assertions: 734 passed and 2 failures outside the changed frontend. |
| JavaScript `node --test tests/JavaScript/*.test.js tests/JavaScript/widgets/*.test.js` | 45 passed, including three new search checks and existing widget/form/notification/navigation checks. |
| Pint on the two changed PHP test files | Passed. |
| `git diff --check` | Passed; Git reports a CRLF normalization notice for the branding template. |
| Taste app lint | 0 failures, 57 warnings. The source scanner counts all shadow declarations, including `none` and focus rings; real overlay shadows, semantic status palette and literal-ID monospace are intentional. |
| Taste login inspection at 1440 / 768 / 390 | 0 failures, 1 warning. The centered authentication sheet intentionally leaves desktop whitespace; no marketing imagery is appropriate in credential entry. |

The first plain `php artisan test` attempt exhausted the host's 128 MB memory limit in an existing PDF-inspection test. The complete rerun used `php -d memory_limit=512M vendor/phpunit/phpunit/phpunit --colors=never` and completed.

The two full-suite failures were also reproduced in a separate targeted run:

1. `SystemWideUserActivityAuditTest::test_logout_requests_retain_the_authenticated_actor` expects `/login`; the existing server redirects to `/`.
2. `CallHistorySyncReliabilityTest::test_call_history_job_queue_matches_horizon_and_local_worker_configuration` expects a queue command string in the README, whose current content links to the operational documentation.

No logout controller, queue configuration or README behavior was changed to silence these assertions.

## Browser matrix

**406 passing responsive/theme checks** across **29 pages**, each at **320, 375, 768, 1024, 1280, 1440 and 1920 CSS pixels**, in both themes. Checks verified HTTP 200, actual measured CSS viewport width, no page-level horizontal overflow after layout settled, and associated visible text/select/date field labels on the authenticated pages. No uncaught JavaScript exceptions were recorded during these page matrices.

The host browser applies a 75% desktop scale. Explicit device-metric emulation was used and the actual document width was asserted, so a nominal browser-window size was not mistaken for the CSS viewport. Final representative screenshots use CDP capture at the measured dimensions.

Pages checked:

- Dashboard, Management Dashboard, Agent Screen, Call History, My Attendance, Reports and a dynamic campaign form.
- Supervisor, Staff Attendance, Records List, Data Master, Capture Records, Disposition Records, Disposition Codes, Field Logic and Extraction.
- User Access, VICIdial Servers, Campaigns, Forms, Email Campaigns, Email Configuration, Lead Hopper, Agent Screen Configuration, Attendance Statuses, System Configuration and Activity Log.
- Login and public Operations Insights.

Raw matrix evidence is available locally at `.playwright-mcp/ui-preview/browser-matrix.json`.

## Interaction checks

- Search receives input focus; a synthetic HTTP 503 displays an error and retry; retry recovers using the real search endpoint; Arrow Down focuses a result link; Escape closes the search dialog.
- Metric dialogs close with Escape and return focus to their trigger.
- Escape declines a confirmation without executing an action.
- The mobile drawer expands its Super Admin section and Escape returns focus to the navigation opener.
- A dynamic form rejects an empty required field, shows entered values in review, closes with Escape and retains the input values.
- Populated dashboard charts render from eight synthetic records. Toggling theme updates chart text/theme without replacing the data.
- Browser login and authorization checks passed for synthetic Admin, Team Leader and Agent users: Super Admin navigation was absent, `/admin/users` returned 403 for all three, and `/admin` returned 200 for Admin/Team Leader and 403 for Agent. The primary matrix used a synthetic Super Admin account.
- Existing Laravel regression tests verify dashboard section controls, campaign scope, form save/validation, branding and email permission/document-delivery boundaries.

## Visual review

Reviewed desktop dashboard in both themes, desktop administration and reports, mobile Agent Screen, mobile email/user interfaces, and the login captures at desktop/tablet/mobile sizes. Review led to a compact signal row, a bounded comparison chart, readable recording filters, a condensed mobile call-control region, labels in configuration forms, and a bottom widget dock.

Final representative images live in `.playwright-mcp/ui-preview/`: `dashboard-desktop-light.png`, `dashboard-desktop-dark.png`, `administration-desktop-light.png`, `reports-desktop-dark.png`, and `agent-mobile-light.png`.

This work measurably improves labels, focus behavior, table semantics, semantic text contrast and motion handling. It is not a full WCAG 2.2 AA certification or a screen-reader audit.

## Integration limits

- All browser data came from a separate SQLite preview. The normal application URL returned 404. Production/MySQL data was not migrated, reset, seeded or edited.
- Reverb was unavailable, and the preview has no configured VICIdial/SIP server. Browser logs contain expected connection-refused and configuration-required responses. Live registration, calls, media, recording, transfer, callbacks and real-time delivery remain unverified.
- No SMTP emails or recipient documents were sent. Existing automated tests cover queue/readiness, document mapping and access rules; provider acceptance/inbox delivery was not tested live.
- Responsive page coverage does not mean that every destructive CRUD action, every integration state or every possible campaign form configuration was exercised in the browser.
- No framework migration or new runtime dependency was introduced. The existing public Operations Insights boundary was retained.

## Skill compliance

Taste (`design-taste-frontend`) and Ponytail were used. OpenSpec and Impeccable instruction files were mistakenly read during the initial inspection before the attachment allowlist was known; neither workflow was executed. No other skills or subagents were used. See the redesign record for the explicit exception.

Final report polish was followed by a successful rebuild, 23 targeted Laravel tests (204 assertions), and 14 repeated report viewport/theme checks. The oversized tinted unavailable KPI was replaced by the shared neutral metric treatment.

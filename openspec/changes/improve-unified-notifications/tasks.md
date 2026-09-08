## 1. Establish the normalized feed contract and durable reads

- [x] 1.1 Extend `tests/Feature/Api/NotificationsApiTest.php` with failing cases for stable source-qualified keys, global newest-first ordering, deduplication, the 25-item limit, `has_more`, a 30-day unread window, and unauthenticated access.
- [x] 1.2 Generate `NotificationReadState` with a migration and factory; define `user_id`, `item_key`, `read_at`, timestamps, the unique user/key constraint, lookup indexes, casts, fillable fields, and the `User` relationship without altering source records.
- [x] 1.3 Add failing tests proving derived-item reads survive cache clearing, single-item and mark-all upserts are idempotent, users cannot read another user's item, and read states older than 90 days can be pruned safely.
- [x] 1.4 Implement `app/Services/Notifications/NotificationReadService.php`, replace cache-authoritative history reads in `app/Services/NotificationService.php`, retain temporary compatibility with existing history cache IDs, and register daily pruning in `routes/console.php` following the project's scheduler pattern.
- [x] 1.5 Run `php artisan test --compact tests/Feature/Api/NotificationsApiTest.php` and the focused read-state test file, then run `vendor/bin/pint --dirty --format agent`.

## 2. Normalize sources and resolve display labels

- [x] 2.1 Add failing service/API cases for mixed supervisor, call/form, and attendance items scoped to the authenticated user and active campaign, including cross-user/cross-campaign exclusion and neutral fallbacks for missing configuration.
- [x] 2.2 Add failing assertions that configured campaign, form, attendance-status, sender, and agent names appear while raw campaign/form/status/notification-class/recipient/VICIdial identifiers do not appear in preview or detail display fields.
- [x] 2.3 Implement a small immutable normalized item/result object under `app/Services/Notifications/`, plus focused database-notification, call/form, and attendance providers with bounded eager-loaded queries and stable `database:`, `history:`, and `attendance:` keys.
- [x] 2.4 Implement `app/Services/Notifications/NotificationLabelResolver.php` using batched campaign/form/status/user lookups, readable status mapping, and neutral unresolved fallbacks; make `NotificationService` merge, deduplicate, globally sort, count, and limit provider results.
- [x] 2.5 Add detail resolution for attendance pairing/open state, authorized call/form context, and supervisor sender/recipient labels without exposing another user's records or internal codes.
- [x] 2.6 Run the focused notification service/API tests and `vendor/bin/pint --dirty --format agent`.

## 3. Add daily performance notifications from dashboard data

- [x] 3.1 Add failing cases to `tests/Feature/DashboardSalesRangeTest.php` and notification API tests proving the same active campaign and 06:00-inclusive/18:00-exclusive range produce identical team count/amount, Top Agent, per-form totals, and leaderboard ordering in the dashboard and notification detail.
- [x] 3.2 Extract the dashboard's default/requested sales range behavior from `app/Http/Controllers/DashboardController.php` into a focused `app/Services/DashboardSalesRangeService.php`, with failing tests for valid/invalid filters and application-timezone 06:00-inclusive/18:00-exclusive day boundaries; keep rendered dashboard behavior unchanged.
- [x] 3.3 Add failing cases for personal KPI resolution through each supported case-insensitive user alias, alias deduplication, no qualifying sales, campaign switching, custom sales rules, and no disposition fallback.
- [x] 3.4 Add failing amount-visibility cases proving disabled dashboard monetary fields are omitted from notification preview/detail payloads rather than hidden only in the browser.
- [x] 3.5 Implement `app/Services/Notifications/DailyPerformanceNotificationProvider.php` so it delegates range resolution to `DashboardSalesRangeService`, KPI calculation to `DashboardStatsService::getSalesKpisForCampaign()`, and visibility to `DashboardLayoutService`; produce one stable `daily:` key per user/campaign/date and map the user's row without recreating aggregation or ranking logic.
- [x] 3.6 Return concise current-user, team-total, and Top Agent preview copy plus detailed personal metrics, per-form totals, and leaderboard data using resolved display names and the existing currency/number formatting conventions.
- [x] 3.7 Run the focused sales-range service test, `php artisan test --compact tests/Feature/DashboardSalesRangeTest.php tests/Feature/Api/NotificationsApiTest.php`, and `vendor/bin/pint --dirty --format agent`.
- [x] 3.8 Add dashboard-aligned current/previous month count and amount comparisons to performance details, including signed changes, percentages, zero-baseline handling, visibility rules, and regression coverage.

## 4. Complete authenticated notification API behavior

- [x] 4.1 Add failing route/controller tests for `GET /api/notifications`, lightweight `GET /api/notifications/summary`, authorized `GET /api/notifications/detail?key=...`, `POST /api/notifications/read`, and the existing `POST /api/notifications/read-all`, including validation, stale keys, non-disclosing 404s, exact unread counts, and throttle/auth middleware.
- [x] 4.2 Refactor `app/Http/Controllers/Api/NotificationsController.php` and add focused summary, detail, and single-read controllers plus Form Requests where validation is non-trivial; return a consistent JSON envelope with `items`, `unread`, `has_more`, and `refreshed_at`.
- [x] 4.3 Update `routes/web.php` with named authenticated routes and keep the existing supervisor send-notification endpoint, authorization, database channel, broadcast channel, and confetti payload backward compatible.
- [x] 4.4 Update `tests/Unit/Notifications/SupervisorUserNotificationTest.php` and `tests/Feature/SupervisorNotificationTest.php` to prove existing database/broadcast payload delivery still works and can reconcile to a stable feed item.
- [x] 4.5 Run the focused API, supervisor notification, and notification unit tests, then run `vendor/bin/pint --dirty --format agent`.

## 5. Build the accessible responsive panel and details modal

- [x] 5.1 Add render assertions to `tests/Feature/ViewLifecycleRenderTest.php` for semantic notification buttons, accessible status regions, Retry, modal labels/description, close control, and no raw-code bindings in visible notification text.
- [x] 5.2 Refactor the notification markup in `resources/views/layouts/app.blade.php` to show distinct initial loading, successful empty, stale/error, and populated states; render each row as a full-width native button with visible unread text/semantics and category iconography from the existing icon component.
- [x] 5.3 Add one shared notification-details modal using the existing modal design system with category-specific performance, attendance, call/form, and supervisor sections; include loading/no-longer-available errors and optional safe named-route links.
- [x] 5.4 Update `resources/css/app.css` with existing semantic tokens for the desktop popover, small-screen viewport-safe panel, 44px minimum interaction targets, wrapping/overflow protection, tabular numeric metrics, visible focus, stale state, reduced motion, and 375/768/1024/1440 layouts.
- [x] 5.5 Update `resources/js/components.js` so row activation marks one item read, opens/fetches detail exactly once, prevents negative badge counts, manages modal focus/restoration, and does not rely on hover.
- [x] 5.6 Run `php artisan test --compact tests/Feature/ViewLifecycleRenderTest.php tests/Feature/Api/NotificationsApiTest.php` and `npm run build`.
- [x] 5.7 Teleport the notification details modal to `body` so the sticky header's backdrop-filter cannot contain the fixed modal backdrop.

## 6. Fix refresh, failure, and soft-navigation lifecycle bugs

- [x] 6.1 Create `tests/JavaScript/notification-dropdown.test.js` with failing Node tests for refresh-on-every-open, summary polling only while visible, in-flight/stale-response protection, last-success retention on errors, empty-versus-error distinction, realtime/REST deduplication, attendance-triggered refresh, and destroy cleanup.
- [x] 6.2 Refactor `notificationDropdown` in `resources/js/components.js` to use request state and sequence/cancellation guards, refresh the full list whenever opened, poll only the lightweight summary at a bounded interval, refresh open content after matching events, retain stale data with Retry on failure, and cap badge presentation at `99+`.
- [x] 6.3 Update `resources/js/attendance-status.js` to emit a scoped attendance-updated event after successful start/end operations and update `resources/js/echo.js` so a replaced Alpine instance cannot inherit a stale notification handler or receive each broadcast more than once.
- [x] 6.4 Add `destroy()` cleanup for timers, visibility/event listeners, outstanding requests, modal/focus state, and Echo teardown before soft navigation; extend `tests/JavaScript/soft-navigate.test.js` or the notification test to cover replacement.
- [x] 6.5 Run `node --test tests/JavaScript/notification-dropdown.test.js tests/JavaScript/soft-navigate.test.js`, `npm run build`, the focused PHP notification tests, and `vendor/bin/pint --dirty --format agent`.

## 7. Browser validation and completion

- [ ] 7.1 Seed or create test fixtures through PHPUnit/factories for all four categories, then use Playwright to verify panel loading, global order, labels, individual reads, Mark all read, and each modal variant with no browser console errors or failed notification requests.
- [ ] 7.2 Use Playwright keyboard-only navigation to open the bell, activate each row with Enter/Space, close with Escape, verify visible focus and focus restoration, and confirm unread/severity meaning is not color-only.
- [ ] 7.3 Use Playwright at 375px, 768px, 1024px, and 1440px plus reduced-motion emulation to confirm no horizontal overflow, unobscured focus, usable 44px targets, correct popover/sheet positioning, and readable performance tables.
- [ ] 7.4 Use Playwright request interception or a controlled test response to verify initial-error Retry, stale-data retention, Reverb-unavailable HTTP fallback, rapid reopen deduplication, and one active subscription after soft navigation.
- [x] 7.5 Run the minimum complete regression set: notification API/service/unit tests, dashboard sales-range tests, attendance status tests, supervisor notification tests, shell lifecycle/render tests, JavaScript notification/soft-navigation tests, `npm run build`, and `vendor/bin/pint --dirty --format agent`.
- [ ] 7.6 Review authorization, PII exposure, query counts, indexes, cache behavior, timezone/range consistency, and migration rollback; then run `/openspec sync`, verify every requirement/scenario against the implemented result, and archive only after all checks pass.

## 8. Extend performance history and campaign-form activity

- [x] 8.1 Add regression coverage for multiple daily performance keys/date-specific details, standard form ownership, campaign capture-form feed items, cross-user exclusion, and durable reads for the new keys.
- [x] 8.2 Extend `DashboardSalesRangeService` and `DailyPerformanceNotificationProvider` to calculate one current/live and bounded historical item per application-timezone business date, with stable keys and historical detail dates.
- [x] 8.3 Add exact `user_id` ownership to new CRM form-history writes, include `AgentCaptureRecord` activity in the normalized feed/detail/read/summary paths, and preserve legacy agent-alias fallback for old rows.
- [x] 8.4 Refresh the open notification panel after standard and campaign capture-form success events, and cover the event lifecycle in JavaScript tests.
- [x] 8.5 Run the focused notification, dashboard-range, view, JavaScript, build, and Pint checks.
- [ ] 8.6 Run browser validation for historical performance/form rows, responsive behavior, accessibility, errors, and soft-navigation lifecycle; sync the canonical specs and archive only after all checks pass.

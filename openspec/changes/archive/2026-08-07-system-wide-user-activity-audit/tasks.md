## 1. Request audit capture

- [x] 1.1 Add `UserActivityRecorder` unit/feature coverage for method, path, route, status, actor, query metadata, and safe failure behavior.
- [x] 1.2 Add `AuditUserActivity` middleware that captures the user before and after `$next`, records authenticated requests after response handling, and never interrupts the original response.
- [x] 1.3 Register the middleware in Laravel 12 `web` and `api` middleware groups and add tests proving read-only, polling, state-changing, logout, guest, and failure-response behavior.
- [x] 1.4 Ensure request properties use the existing recursive sanitizer and add regression coverage for sensitive query values and omitted bodies/headers.

## 2. History and realtime presentation

- [x] 2.1 Extend `ActivityLogEntry` with request metadata, HTTP-method actions, response-status severity, and request-aware searching/filtering.
- [x] 2.2 Include every current user in the Activity Log actor selector and add request-event action visibility to the terminal filters.
- [x] 2.3 Add feature tests proving requests from Agent, Team Leader, Admin, and Super Admin users are returned with correct actor labels and normalized request details.
- [x] 2.4 Verify realtime append and polling reconciliation for newly recorded request activities without duplicate entries.

## 3. Validation and OpenSpec completion

- [x] 3.1 Run focused PHPUnit and Node tests with a red-green cycle for request auditing.
- [x] 3.2 Run Pint, the complete PHPUnit suite, JavaScript tests, Vite build, and Playwright verification of actor filters and request entries.
- [x] 3.3 Sync the capability to the main OpenSpec specs and archive the completed change.

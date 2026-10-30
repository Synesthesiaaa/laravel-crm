# Validation record

## Automated checks

- New HTTP/workflow tests were observed failing before the routes/UI existed. The transient deadlock regression was also observed failing, then passing after recoverable locks were changed to release the same job and retry its unchanged checkpoint.
- Final complete PHP suite: `php -d memory_limit=1G vendor/phpunit/phpunit/phpunit --no-coverage` — **841 tests, 4,679 assertions passed**, 1:49.137. Coverage was explicitly disabled because the local runtime has no coverage driver.
- An earlier `php -d memory_limit=1G artisan test --no-coverage` run exhausted the spawned test process's 128 MiB limit. Running PHPUnit directly applied the intended memory limit and passed the complete suite; there are no unresolved test failures.
- `npm run test:js` — **15 tests passed**, including stale polling-response isolation, error recovery/lifecycle cleanup, and suppression of raw server exception text.
- `npm run build` — passed, 281 modules transformed.
- Pint on the 17 affected PHP targets — passed; final changed service/regression test also passed after the locking fix.
- `git diff --check` — passed.
- `php artisan route:list --path=admin/users --except-vendor` — existing user CRUD plus six import endpoints registered.
- The new migration was applied and rolled back in a separate in-memory SQLite database; all three new tables were removed and the existing users table remained.

## Browser and real queue checks

The configured MySQL server refused connections at localhost:3306. It was not reconfigured or replaced. Browser validation used an isolated SQLite database under `.codex/validation/user-import-ui`, synthetic accounts/campaigns, a server bound to 127.0.0.1:8138, test hashing cost, and a real database queue worker on the dedicated user-imports connection. No real CRM accounts or external telephony systems were modified.

Using Playwright CLI after the available MCP browser reported a busy profile:

- Signed in through the actual login form and opened Super Admin User Access.
- Invalid CSV headers produced an inline recovery message.
- Uploaded a five-row file with higher-role opt-in: preview **5 checked / 3 eligible / 1 skipped / 1 rejected**, then confirmed creation with the same final counts. Team Leader was stored correctly and visible after refreshing the staff list.
- Downloaded the actual template and row-error attachment. Row errors contained static password guidance, not uploaded passwords or email values.
- Simulated a status 503 response containing a fake secret: retained the last counts, displayed safe recovery text, and recovered with Retry status without displaying the response's secret.
- Repeated the file after completion: **0 eligible / 4 skipped / 1 rejected**, with no start action. Discard upload produced cancelled status.
- A 127-row file completed over three reservations in each phase: **127 checked / 125 eligible or created / 1 skipped / 1 rejected**. Polling observed intermediate processed=100 before terminal processed=127. No pageerror events occurred in these workflows.
- Concurrent SQLite session/browser writes exposed a transient database deadlock in the initial implementation. After the test-first fix, the same multi-slice browser import completed. Recoverable lock exceptions are never serialized or logged with their potentially sensitive original bindings.
- Separate Admin and Agent browser contexts each received 403 for User Access and import status; automated tests additionally covered Team Leader, unauthenticated access, and different Super Admin ownership.
- Verified final 375/768/1024/1440 widths, both themes, no page-level horizontal overflow, 44px primary controls, and unobstructed upload trial-click/hit tests. Waited for responsive widget state/layout to settle before measurement.
- Keyboard Tab to the role option and upload action produced visible solid 2px focus outlines.

## Design and browser limits

- Used Impeccable context, inherited the existing Operate-mode design system, recorded the surface contract, and reviewed the rendered result.
- The mechanical detector ran once on the three changed UI targets and returned no findings (`[]`).
- The independent finish reviewer identified one fixture-position issue: an existing draggable quick-form launcher covered the upload action at 1024px. The synthetic fixture position was cleared without changing widget source. Replacement captures and hit tests resolved the listed issue; the reviewer returned `ship` at that fix's scope. This is not a claim of an independent exhaustive whole-application audit.
- The separate design documenter verified that this ordinary extension inherits existing tokens/components and needs no global design-system changes. PRODUCT.md, DESIGN.md, and the pre-existing stale design sidecar were preserved.
- Final screenshots were captured and visually inspected at all four widths. Evidence is retained locally under `.codex/validation/user-import-ui`; development captures are not runtime application assets.
- The isolated shell also emitted existing report-only Alpine CSP messages, unavailable Reverb WebSocket errors, and 422 responses for absent synthetic SIP/quick-form configuration. Expected injected 503 and invalid-upload 422 responses were part of recovery tests. These were not presented as a clean whole-application console; no importer JavaScript exceptions remained.
- Native Windows worker timeout/signal behavior, production hashing throughput, MySQL collation/race acceptance, live integrations, and deployment against the currently offline configured database remain unverified. The UI displays the effective PHP upload limit; the local CLI default was 2M/8M, while the isolated web fixture used 20M/21M overrides.

## Completion

Operator deployment, queue, upload-limit, cleanup, and UI instructions are in `docs/bulk-user-import.md` and `docs/operations/queues-and-workers.md`. The UI operates without shell access for staff; deployment still requires the additive migration and a matching background consumer.

# Validation

- Confirmed source cause: `resources/js/http-bootstrap.js` defines the shared Axios timeout as 5,000ms; the CSV upload previously supplied no override.
- Two new JavaScript regressions failed before implementation: an upload requiring eight seconds received the generic failure instead of queued state, and timeout/server failure messages were indistinguishable.
- Original built frontend in Playwright displayed the exact reported generic message after **5,104ms** while a transport response was delayed eight seconds.
- Rebuilt frontend in Playwright accepted an actual synthetic multipart upload held in the native request path for eight seconds: **8,408ms**, HTTP **202**, queued status, empty error. The queued preview was then cancelled to remove its private source. No real accounts were created.
- Browser fixture: separate SQLite database and localhost:8139 server, global queue default sync, no worker consuming the dedicated database queue. Existing unrelated Reverb/SIP/quick-form fixture warnings remain separate from this import result. No layout or visual-system changes were made.
- Added real database-queue HTTP tests without Queue::fake: upload inserts a jobs row on user-imports despite a sync global default, and a missing jobs table rolls back import metadata and deletes source credentials. Both tests passed; job payload excludes the fixture password.
- `npm run test:js`: **17 passed**.
- `npm run build`: passed.
- `php -d memory_limit=1G vendor/phpunit/phpunit/phpunit --no-coverage`: **843 tests, 4,692 assertions passed**, 1:46.223. No coverage driver is available locally.
- Pint on the new PHP test, `git diff --check`, and strict change validation passed.
- The deployed server is not connected. The supplied message is also the fallback for other network/HTTP failures, so this evidence proves a reproducible client bug and its fix; it does not establish that all deployed-server failure causes have been eliminated.
- Both Laravel Boost connections returned transport-closed errors. Local runtime inspection confirmed the configured local MySQL connection is unavailable; no local state was used to claim deployed connectivity.
- Deployment requires publishing the newly built frontend assets and reloading the browser. No additional migration is introduced by this fix. Existing queue worker/server prerequisites remain unchanged.

## 1. Restore active Vicidial sessions after reload

- [x] 1.1 Extend the iframe reconnect helper so it restores `ready`, `paused`, and `in_call` sessions as well as `login_pending`.
- [x] 1.2 Rebuild the iframe URL from the active session when `last_iframe_url` is missing.
- [x] 1.3 Clear the widget back to idle when the backend reports `logged_out`.

## 2. Regression coverage

- [x] 2.1 Add unit coverage for rebuilding the iframe URL from an already-ready session with no cached iframe URL.

## 3. Validation

- [x] 3.1 Run `php artisan test --compact tests/Unit/Services/VicidialSessionServiceTest.php --filter=test_get_aligned_iframe_url_rebuilds_ready_session_without_last_iframe_url`.
- [x] 3.2 Run `vendor/bin/pint --dirty --format agent`.
- [x] 3.3 Run `npm run build`.
- [x] 3.4 Verify in the browser that an active session restores its iframe after reload and that pause/logout still work.

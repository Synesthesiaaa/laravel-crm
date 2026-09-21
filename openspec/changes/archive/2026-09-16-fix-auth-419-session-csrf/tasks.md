## 1. Exception recovery

- [x] 1.1 Register route-aware `TokenMismatchException` browser recovery in `bootstrap/app.php`.
- [x] 1.2 Keep JSON/API and unrelated 419 responses on Laravel's default exception path.

## 2. Regression coverage

- [x] 2.1 Add focused tests for stale-token recovery destinations and safe input handling on login and logout.
- [x] 2.2 Add a login-to-logout feature regression that exercises token extraction across session regeneration.

## 3. Verification and specification closeout

- [x] 3.1 Run the focused PHPUnit tests and Laravel Pint on modified PHP files.
- [x] 3.2 Verify the browser login request against the local Apache application and inspect browser errors.
- [x] 3.3 Synchronize the OpenSpec delta and archive the completed change after validation.

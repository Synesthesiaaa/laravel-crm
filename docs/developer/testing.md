# Testing and validation

This documentation modernization did not run tests, migrations, workers, browsers, remote API requests, or email sends. The commands below are developer procedures for an isolated environment, not recorded successful checks.

[phpunit.xml](../../phpunit.xml) defines Unit and Feature suites, SQLite `:memory:`, array cache/session/mail, null broadcasting, and synchronous default queue. These settings do not automatically fake custom connections or every external client; inspect each test's fakes and runtime overrides before execution. Tests commonly use framework database reset/refresh helpers. Never point test commands at production data.

| Area | Useful test sources |
|---|---|
| Auth/session/CSRF | `tests/Feature/LoginTest.php`, `AuthCsrfRecoveryTest.php`, `Admin/LoginRateLimitTest.php` |
| Permission/privacy | `BroadcastChannelAuthorizationTest.php`, `PrivateRuntimeSecurityTest.php`, admin feature tests |
| Call state/dial | `tests/Feature/Api/CallDialApiTest.php`, `CallControllerHangupTest.php`, `CallStatusApiTest.php`, `tests/Unit/Services/Telephony/CallStateRaceConditionTest.php` |
| Webhooks | `tests/Feature/Api/AmiWebhookTest.php`, `VicidialEventsWebhookTest.php`, `VicidialCallUrlControllerTest.php` |
| History/reporting | `CallHistorySyncReliabilityTest.php`, `TelephonyReportsDataAccuracyTest.php`, `tests/Fixtures/Vicidial` |
| Forms/capture | `FormSubmissionTest.php`, `FormStorageNullabilityTest.php`, `Api/AgentCaptureApiTest.php`, admin form tests |
| Email | `tests/Feature/Admin/EmailCampaignsTest.php`, `EmailCampaignLocalSendingTest.php`, `EmailDocumentDeliveryTest.php` |
| Browser modules | `tests/JavaScript/*.test.js`, `tests/JavaScript/widgets/*.test.js` |

Examples after isolating environment and dependencies:

```console
php artisan test --filter=CallDialApiTest
php artisan test --filter=BroadcastChannelAuthorizationTest
php artisan test
node --test tests/JavaScript/*.test.js tests/JavaScript/widgets/*.test.js
npm run build
vendor/bin/pint --test
```

The existing [.github workflow](../../.github/workflows/ci.yml) targets PHP 8.2/8.3, which conflicts with the current lockfile's Symfony 8 requirement of PHP 8.4+. This documentation does not repair or claim a green CI run.

`package.json` currently declares only `build` and `dev` scripts: there is no `npm test` or `npm run lint` contract. `composer test` clears configuration before running Artisan tests, so it changes cached state. Review scripts before use.

For UI changes use Playwright against a deliberately isolated application with disposable accounts/data; verify auth/roles, session expiry, campaign resolution, forms, responsive widths, keyboard access, browser console/network failures, and websocket reconnect/poll fallback. Calls, recording, transfer, remote lead writes, SMTP, and unsubscribe are side effects: use fakes or an explicitly authorized sandbox. Browser success cannot certify real SIP audio or remote VICIdial behavior.

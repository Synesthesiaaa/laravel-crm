## 1. Campaign Separation

- [x] 1.1 Remove any CRM campaign gating from the Vicidial session request path so softphone requests can complete on the telephony campaign.
- [x] 1.2 Keep CRM campaign session state unchanged while preserving Vicidial campaign bootstrap and persistence.

## 2. Softphone Readiness

- [x] 2.1 Update Vicidial session login and verification handling so live Vicidial confirmation wins even when the CRM campaign differs.
- [x] 2.2 Keep the confirmed Vicidial campaign in telephony state only and do not backfill it into the CRM campaign session.
- [x] 2.3 Preserve timeout and failed states for real iframe, network, authentication, or Vicidial availability failures.

## 3. Regression Coverage

- [x] 3.1 Add or update Vicidial session API and service tests for the mismatched-campaign ready path.
- [x] 3.2 Add or update widget tests to confirm the CRM campaign does not block softphone startup or readiness.
- [x] 3.3 Run `php artisan test --compact tests/Feature/Api/VicidialSessionApiTest.php tests/Unit/Services/VicidialSessionServiceTest.php tests/Unit/Services/TelephonyBootstrapServiceTest.php tests/Feature/PhoneWidgetTest.php`.
- [x] 3.4 Run `vendor/bin/pint --dirty --format agent`.
- [x] 3.5 Run `npm run build`.

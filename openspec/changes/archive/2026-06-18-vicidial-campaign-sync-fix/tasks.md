## 1. Backend campaign sync

- [x] 1.1 Add regression coverage for a live Vicidial session that confirms a different campaign than the CRM default.
- [x] 1.2 Update the Vicidial session service so successful live verification persists the confirmed Vicidial campaign as the active session campaign.
- [x] 1.3 Update the verification and mismatch payloads so campaign equality is no longer treated as a readiness requirement when Vicidial reports a live agent.

## 2. Frontend campaign adoption

- [x] 2.1 Update the widget login and verify handling so the confirmed campaign from Vicidial responses is copied into local widget state.
- [x] 2.2 Ensure reconnect, pause, logout, and status polling keep using the synced campaign after the browser reloads.
- [x] 2.3 Simplify the mismatch guidance so the UI only reports real live-agent failures instead of instructing the user to match CRM and Vicidial campaign names.

## 3. Tests and verification

- [x] 3.1 Update `tests/Feature/Api/VicidialSessionApiTest.php` for the synced campaign behavior and removed mismatch failure.
- [x] 3.2 Update `tests/Unit/Services/VicidialSessionServiceTest.php` for the live-ready mismatch case and persisted campaign reuse.
- [x] 3.3 Update `tests/Feature/PhoneWidgetTest.php` for boot and reconnect behavior using the synced campaign.
- [x] 3.4 Run `php artisan test --compact tests/Feature/Api/VicidialSessionApiTest.php tests/Unit/Services/VicidialSessionServiceTest.php tests/Feature/PhoneWidgetTest.php`, then `vendor/bin/pint --dirty --format agent`, then `npm run build`.

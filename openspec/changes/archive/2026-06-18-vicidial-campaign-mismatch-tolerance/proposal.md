## Why

The softphone should stay usable when the Vicidial campaign and the CRM campaign do not match. Right now, the campaign mismatch path can still look like a connection problem to agents, even though the CRM campaign is supposed to remain its own value and the Vicidial session should be the only source of truth for telephony readiness.

## What Changes

- Keep the CRM campaign session value independent from the Vicidial campaign used by the softphone.
- Allow Vicidial session login, iframe recovery, verification, pause, logout, and status to continue even when the telephony campaign differs from the CRM campaign.
- Update the softphone state handling so campaign mismatch alone does not surface as `timeout`, `connecting`, or failure messaging.
- Preserve hard failures for real problems such as missing VICIdial credentials, unreachable VICIdial services, or iframe load failures.
- **BREAKING** The Vicidial softphone flow will no longer treat CRM/Vicidial campaign equality as a readiness requirement.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities
- `platform-stabilization`: Vicidial softphone behavior now tolerates CRM/Vicidial campaign divergence while keeping the CRM campaign value unchanged.

## Impact

- `routes/web.php` and the Vicidial session controller flow, if route-level CRM campaign gating needs to be relaxed for softphone requests.
- `app/Services/Telephony/VicidialSessionService.php` and `app/Services/Telephony/TelephonyBootstrapService.php` for readiness and bootstrap state handling.
- `resources/js/vicidial-session.js`, `resources/js/phone-widget.js`, and `resources/views/partials/phone-widget.blade.php` for state transitions and user-facing messaging.
- `tests/Feature/Api/VicidialSessionApiTest.php`, `tests/Unit/Services/VicidialSessionServiceTest.php`, `tests/Unit/Services/TelephonyBootstrapServiceTest.php`, and `tests/Feature/PhoneWidgetTest.php` for mismatch regressions.

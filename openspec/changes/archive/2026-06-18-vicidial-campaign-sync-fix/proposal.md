## Why

Users can reach a valid live Vicidial session even when the campaign selected in Vicidial does not match the CRM campaign default. Today the softphone still treats that mismatch as a failure condition, which blocks successful verification and leaves reconnect and status flows stuck even though Vicidial has already accepted the session.

## What Changes

- Persist the live Vicidial campaign as the active telephony campaign for the current session after login or verification confirms the agent is live.
- Treat a live Vicidial agent session as success even when the CRM campaign value differs from the Vicidial campaign.
- Update the softphone widget to adopt the confirmed campaign from Vicidial responses so reloads and reconnects continue on the same session.
- Keep pause, logout, iframe recovery, and status sync tied to the synced telephony campaign.
- Add regression coverage for campaign-mismatch login, readiness, and reload behavior.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `platform-stabilization`: The softphone campaign requirement changes so confirmed Vicidial state becomes authoritative for the session and campaign mismatches no longer block readiness.

## Impact

- Backend telephony session confirmation and campaign persistence in `app/Services/Telephony/VicidialSessionService.php` and `app/Http/Controllers/Api/VicidialSessionController.php`.
- Frontend session state sync in `resources/js/phone-widget.js`.
- Existing telephony bootstrap and reload behavior that reads `vicidial_campaign`.
- Regression tests covering session verification and widget boot and reconnect flows.
- No schema changes are expected.

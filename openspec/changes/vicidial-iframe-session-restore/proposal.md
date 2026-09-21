## Why

The floating softphone keeps Vicidial session state in the backend, but after a reload it only reattaches the iframe during mid-login recovery. If the agent was already ready, paused, or in call, the widget can come back looking logged in while the embedded Vicidial iframe is blank, which makes the session feel unreliable and breaks the expected restore flow.

## What Changes

- Restore the Vicidial iframe when an active session already exists after reload, not only while login is pending.
- Rebuild the iframe URL from the active session when the cached URL is missing, so recovery does not depend on `last_iframe_url`.
- Keep the widget phase aligned with the restored session state and clear stale idle/online indicators when the backend session changes.
- Add regression coverage for restoring active sessions and rebuilding the iframe URL.

## Capabilities

### New Capabilities
- None

### Modified Capabilities
- `platform-stabilization`: expands softphone reload and reconnect behavior so active Vicidial sessions restore their iframe and widget state after a browser refresh.

## Impact

- Frontend reconnect logic in `resources/js/vicidial-session.js` and `resources/js/phone-widget.js`.
- Backend iframe URL rebuilding through the existing Vicidial session service and controller flow.
- Regression coverage in `tests/Unit/Services/VicidialSessionServiceTest.php`.

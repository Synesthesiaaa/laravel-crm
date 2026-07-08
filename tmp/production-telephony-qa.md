# Production Telephony QA - 2026-07-01

Tested against:
- CRM: `https://crm.cidglobal.ph`
- VICIdial / AGC: `https://dial.cidglobal.ph`

Scope:
- Agent screen
- Softphone popup
- Live call initiation
- Transfer
- Recording
- Disposition

## Executive Summary

I found three production issues that need attention:

1. The CRM production bundle is stale and still calls removed VICIdial endpoints.
2. The VICIdial AGC popup repeatedly requests `get2post.php`, which returns 404.
3. Live call initiation from the CRM was blocked in the QA run because the hidden VICIdial session never stayed in a verified `ready` state.

I also confirmed that transfer and recording are disabled by production feature flags, so those UI/API paths are intentionally blocked right now.

## What I Verified

- The CRM login works with the shared dev credentials.
- The softphone popup opens and logs into VICIdial with the expected credentials.
- The popup loads `calendar_db.js`, `viciphone.js`, and the webphone iframe.
- The popup repeatedly requests `https://dial.cidglobal.ph/agc/get2post.php`.
- Transfer and recording endpoints return `403` with "disabled by administrator".
- The disposition modal renders valid codes and can be submitted from the UI.

## Findings

### 1. Stale production CRM bundle is still calling removed VICIdial endpoints

Severity: High

Evidence:
- Production browser console hit 404s for:
  - `/api/vicidial/session/agent-campaigns?context_campaign=mbsales`
  - `/api/vicidial/session/select-campaign`
- The old production asset hash was still being served from:
  - `https://crm.cidglobal.ph/build/assets/app-DNM1B9df.js`

Impact:
- This breaks campaign/session bootstrap on production.
- It can cause false errors in the agent screen and can destabilize the softphone bootstrap path.

Recommendation:
- Redeploy the rebuilt frontend assets from current source.
- Purge any CDN, proxy, or browser cache that may still be serving the stale `app-DNM1B9df.js` bundle.
- After deploy, confirm production is serving the new build hash and that the removed endpoints no longer appear in network traffic.

### 2. VICIdial AGC popup repeatedly calls `get2post.php`, and the endpoint is 404

Severity: High

Evidence:
- Popup login succeeds and lands on the VICIdial agent screen.
- The popup resource list repeatedly includes:
  - `https://dial.cidglobal.ph/agc/get2post.php`
- Response capture shows:
  - `GET https://dial.cidglobal.ph/agc/get2post.php -> 404`
  - repeated multiple times
- Related AGC resources load successfully:
  - `calendar_db.js` -> 200
  - `viciphone.php` iframe -> 200
  - `viciphone.js` -> 200

Impact:
- VICIdial is trying to use an AGC push path that does not exist on the dial host.
- This can break event push, session coordination, or status propagation.
- It also creates noisy console errors and makes the popup look unstable even when login succeeds.

Recommendation:
- Fix the VICIdial server-side AGC configuration so the `get2post.php` path exists or update the AGC push configuration to point at the correct endpoint.
- Verify the dial host deployment, not just the CRM app.
- Add a VICIdial-side smoke check for `get2post.php` so this breaks loudly during maintenance instead of in production QA.

### 3. Hidden CRM session never stayed live enough to place a call in this QA run

Severity: High

Evidence:
- CRM agent screen state remained:
  - `callState: idle`
  - `vicidialStatus: login_pending`
  - later timing out back to `login_pending`
- `POST /api/call/dial?campaign=mbsales` returned:
  - `422`
  - `VICIDIAL_AGENT_NOT_LOGGED_IN`
  - message: "Log into VICIdial for this campaign on the agent screen first, then wait until Online."

Impact:
- Outbound calling is blocked until the hidden VICIdial iframe reaches a verified live state.
- This also makes downstream live-call verification unreliable.

Recommendation:
- Investigate why the hidden iframe is not maintaining a live `ready` state in production QA.
- Check whether the popup login is displacing the hidden CRM session.
- Verify the browser environment used for QA has microphone and WebRTC permissions, because the AGC popup also surfaces a WebRTC warning message.

### 4. Transfer controls are intentionally disabled in production

Severity: Medium

Evidence:
- Production feature flags show:
  - `transfer_controls: false`
  - `recording_controls: false`
- Direct API calls returned:
  - `403` with feature `"transfer_controls"`
  - `403` with feature `"recording_controls"`

Impact:
- The transfer and recording UI is hidden, and the backend blocks those endpoints.
- This is correct if production is meant to run without those features.

Recommendation:
- If the intent is to test or use these flows in production, enable the corresponding `telephony_feature_*` settings.
- If they are intentionally disabled, document that clearly so QA does not expect them to work.

### 5. Disposition modal is present and selectable, but I would not call the persistence path fully validated

Severity: Medium

Evidence:
- The disposition modal rendered with options:
  - `TEST`
  - `SALE`
  - `CBH`
  - `CBW`
  - `CBC`
  - `DNC`
  - `NAN`
  - `NA`
  - `BUSY`
  - `OTHER`
- The modal can be opened and a code can be selected.

Concern:
- In this QA run, the live call never reached a stable verified agent state, so I would not treat disposition persistence as fully production-validated yet.

Recommendation:
- Re-run disposition save after the call session is confirmed live.
- Add a small end-to-end smoke test that asserts a disposition record is created after a verified live call.

## Secondary Observations

- The popup login page shows the active credentials and session ID correctly.
- The AGC popup currently shows the message that another live session using the same user ID was disabled. That is expected behavior when the same credentials are reused in a second browser.
- The popup body includes a WebRTC warning message. This may be environment-specific in headless QA, but it is worth checking on a real operator workstation.

## Priority Order

1. Redeploy the CRM frontend bundle so production stops serving the stale asset.
2. Fix the VICIdial AGC `get2post.php` 404 on the dial host.
3. Re-test hidden iframe session stability and outbound dial readiness.
4. Decide whether transfer and recording should remain disabled in production.
5. Re-run disposition persistence after a verified live call.

## Notes

- I used the same shared credentials as development.
- The production CRM and the VICIdial popup behave differently when the popup session takes over the same user ID.
- The call and popup flows are coupled. If the popup is opened too early, it can displace the hidden CRM session and make outbound dialing fail.
- I did not modify production settings.


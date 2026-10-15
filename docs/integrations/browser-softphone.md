# Browser softphone

The CRM agent screen and persistent phone widget coordinate several independent states: authenticated browser session, selected CRM campaign, local VICIdial session, SIP registration/media, call lifecycle, and pending disposition. A green state in one subsystem does not verify the others.

| Module | Responsibility |
|---|---|
| `resources/js/phone-widget.js` | Widget UI and user interactions |
| `resources/js/telephony-core.js` | SIP.js user agent/session/media operations |
| `resources/js/telephony-media-path.js` | Chooses ownership of browser media |
| `resources/js/vicidial-session.js` | VICIdial session login/iframe/verify/polling |
| `resources/js/agent-vicidial-events.js` | Interprets agent push/poll updates |
| `resources/js/echo.js` | Private application event subscriptions |
| `resources/js/agent-capture-webform.js` | Capture webform integration |

`TELEPHONY_MEDIA_PATH` defaults to `sipjs`: CRM SIP.js owns audio; the VICIdial iframe supplies session UI. In `viciphone`, iframe ViciPhone owns audio and CRM should not register its SIP.js stack. Legacy `both` permits two owners and can create duplicate extension registration; health checks warn about this mode. Choose ownership deliberately for a deployment rather than inferring it from whether an iframe exists.

Typical flow is CRM login/bootstrap → agent session login/iframe/verify → SIP readiness in the selected media path → dial → state updates through push/poll/AMI → hangup → disposition. Dial can be blocked by an active call, missing credentials/session or pending disposition. Store UI state carefully across soft navigation and remove old listeners; duplicate initialization can look like duplicated telephony events.

Browser permission, secure-context requirements, autoplay/audio device behavior, WSS trust, NAT/ICE and external phone configuration must be verified on target browsers. No microphone permission, SIP registration, audio path, real number, transfer, recording or live agent-session behavior was exercised in this documentation review. Use a purpose-built sandbox and explicit authorization before browser testing that can initiate calls.

[API reference](../developer/api-reference.md) · [SIP/WebRTC](sip-webrtc.md) · [call diagram](../diagrams/call-flow.md)

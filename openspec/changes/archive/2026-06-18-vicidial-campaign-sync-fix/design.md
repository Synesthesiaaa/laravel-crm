## Context

This change targets the Vicidial softphone flow in a Laravel 12 CRM. The current implementation already resolves the campaign from the existing bootstrap chain, uses `vicidial_campaign` for widget startup, and stores telephony session state in `VicidialAgentSession`. The bug is not campaign lookup itself; it is that the live Vicidial session can differ from the CRM campaign value, and the verification path still treats that mismatch as a failure even when Vicidial has already accepted the agent.

The affected flow spans backend services, the API controller, and the floating widget runtime:
- `VicidialSessionService` creates and verifies the telephony session.
- `VicidialSessionController` exposes login, verify, iframe reload, pause, logout, and status endpoints.
- `phone-widget.js` and `vicidial-session.js` keep the browser session state and reconnect behavior in sync.
- `TelephonyBootstrapService` and `TelephonyCampaignResolver` already provide the startup fallback chain and should remain intact.

The design must keep the current campaign source chain for startup, but once Vicidial confirms a live session, that confirmed campaign becomes the authoritative telephony campaign for follow-up requests.

## Goals / Non-Goals

**Goals:**
- Persist the Vicidial-confirmed campaign as the active telephony campaign for the current session.
- Treat live Vicidial state as the success condition, even when the CRM campaign differs.
- Keep iframe recovery, pause, logout, status sync, and reconnect working after the active campaign changes.
- Update the widget state from authoritative Vicidial responses so reloads do not fall back to the wrong campaign.
- Add regression coverage for mismatch, reconnect, and live-ready cases.

**Non-Goals:**
- Do not change the login-page campaign picker.
- Do not introduce a new schema column or a new persistent campaign preference model.
- Do not weaken real login, credential, or server errors.
- Do not replace the current Vicidial or SIP.js integration.

## Decisions

### 1. Make the Vicidial-confirmed campaign authoritative after live confirmation

Once Vicidial reports a live agent session for the current user, the confirmed campaign should overwrite the temporary bootstrap value in the active telephony session context.

Why this approach:
- It matches the real operating state instead of preserving a stale CRM default.
- It keeps subsequent telephony actions aligned with the campaign that Vicidial is actually using.
- It avoids adding another persisted campaign field when the current session storage already has a place to hold the active value.

Alternatives considered:
- Keep the CRM campaign canonical and only loosen the error message. Rejected because pause, logout, reconnect, and iframe recovery would still target the wrong campaign.
- Add a new `active_vicidial_campaign` storage field. Rejected because it adds migration and maintenance cost without solving a problem the existing session key can already solve.

### 2. Keep the existing bootstrap fallback chain, but let live Vicidial state override it

The existing fallback chain remains the startup source of truth: `session('vicidial_campaign')` -> user default campaign -> `mbsales`. That chain is still useful when the browser opens before Vicidial has confirmed the session or when the user has no live session yet.

Why this approach:
- It preserves current startup behavior and minimizes blast radius.
- It gives the widget a stable campaign immediately on load.
- It still allows Vicidial to correct the active campaign once a live session is verified.

Alternatives considered:
- Recompute the campaign from Vicidial on every request. Rejected because startup and reconnect flows would become slower and more brittle.
- Force all flows to use the CRM default campaign. Rejected because it recreates the mismatch bug.

### 3. Keep readiness tied to live agent evidence, not campaign equality

Verification should succeed when Vicidial shows the expected user as live and the session is usable. Campaign equality should not be a separate requirement for readiness.

Why this approach:
- The business requirement is "can the agent use the softphone right now?" not "do the CRM and Vicidial campaign strings match?"
- It avoids false negatives when the agent has already logged in correctly under a different but valid Vicidial campaign.

Alternatives considered:
- Require both live status and equal campaign names. Rejected because it turns a valid live session into a false failure.
- Treat every mismatch as a warning only. Rejected because the browser still needs a definitive ready or pending state.

### 4. Sync the browser state from the login and verify responses instead of adding a new endpoint

The existing login and verify responses already include enough alignment data to update the browser state. The widget should copy the confirmed campaign into its local Alpine state after Vicidial accepts the session.

Why this approach:
- It avoids an extra API surface area.
- It keeps the browser state in sync with the server response that already proved the session is live.
- It reduces the chance that reconnect logic keeps using stale state after reload.

Alternatives considered:
- Add a dedicated "current campaign" endpoint. Rejected because the data already exists in the login and verify responses.
- Leave the browser state unchanged and always rely on the next status poll. Rejected because the next action could still run against the stale campaign.

### 5. Reuse existing session storage and local telephony session records

The implementation should continue to use the current Vicidial session storage and `VicidialAgentSession.campaign_code` rather than introducing a new persistence layer.

Why this approach:
- It keeps the change small and focused.
- It avoids a schema migration.
- It keeps reload, reconnect, pause, logout, and status logic all reading the same authoritative campaign.

Alternatives considered:
- Build a separate persistence table for Vicidial campaign sync. Rejected because it is unnecessary for the current scope.

## Risks / Trade-offs

- [Campaign drift if the wrong campaign is persisted too early] -> Mitigation: only promote the Vicidial-confirmed campaign after a successful live-session confirmation, not on an initial pending login attempt.
- [Frontend and backend can disagree briefly during reconnect] -> Mitigation: update the widget state from the authoritative response payload before running later actions.
- [A genuine login failure could be masked by over-relaxing verification] -> Mitigation: keep credential, iframe, and server failures intact; only remove campaign equality as a success blocker.
- [Existing sessions may still start from the old fallback value on first load] -> Mitigation: keep the current bootstrap chain and let live confirmation replace the active campaign as soon as it is available.
- [Duplicated sync logic across backend and frontend] -> Mitigation: keep the authoritative campaign update in the session service and mirror it once in the widget response handling.

## Migration Plan

1. Update the backend session service so a successful live Vicidial confirmation persists the confirmed campaign as the active telephony campaign.
2. Update the controller and widget response handling so the browser adopts the authoritative campaign from Vicidial login and verify responses.
3. Keep the existing bootstrap fallback chain in place so reloads still have a valid campaign before Vicidial confirmation arrives.
4. Update the focused PHPUnit coverage to include a mismatched CRM/Vicidial campaign case that still reaches ready.
5. Run the focused tests, then Pint, then the frontend build.

Rollback strategy:
- Revert the application code if the new sync path causes regressions.
- No database rollback is needed because the design does not add or modify schema.

## Open Questions

None at this time. The confirmed Vicidial campaign should become authoritative after live confirmation, and CRM campaign equality should not block readiness.

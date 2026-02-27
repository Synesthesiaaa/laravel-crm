# Agent Telephony Screen – Build Plan

**Date:** 2026-02-25  
**Goal:** Make calls and features perfectly functioning with minimal errors – softphone (priority), lead hopper for predictive dialing.  
**Scope:** Frontend agent screen, backend orchestration, lead hopper, UI consistency.

---

## Executive Summary

| Area | Current State | Target State |
|------|---------------|--------------|
| **Softphone** | Partially working; dial assumes connected immediately; hangup doesn't invoke SIP.js | Fully working: correct state flow, SIP.js integration, mute/hold wired |
| **Lead Hopper** | Does not exist | Next-lead API, auto-load after disposition, manual "Next Lead" button |
| **Agent Screen Fields** | AgentController doesn't pass fields; view expects different schema | Load AgentScreenField for campaign, map to view format |
| **Echo/Reverb** | Can fail silently; agent page may not have userId for subscription | Ensure Echo init, pass data-user-id, fallback polling |
| **Form Save** | Placeholder (toast only, no API) | Real form submission to capture API |

---

## Phase 1 – Softphone Fixes (Critical)

### 1.1 Fix dial() State Flow

**Problem:** Agent `dial()` sets `callState = 'connected'` immediately after API returns. The call has not actually connected yet – SIP.js receives INVITE and auto-answers asynchronously.

**Fix:**
- After `POST /api/call/dial` or `GET /api/vicidial/proxy?action=originate` succeeds:
  - Set `callState = 'ringing'` (not `connected`)
  - Store `sessionId` from response
- Rely on **one** source of truth for connected state:
  - **Option A (WebRTC):** TelephonyCore session state (`SessionState.Established`) → sets `connected`
  - **Option B (Reverb):** `CallStateChanged` broadcast with `to_status = answered|in_call` → sets `connected`
  - **Option C (Polling):** `/api/call/status` returns active call with status → rehydrate

**Implementation:**
- In `agent/index.blade.php` `dial()`: Remove `callState = 'connected'` and timer start on API success.
- Set `callState = 'ringing'`, `sessionId = res.data.session_id`.
- Echo subscription or TelephonyCore delegate will transition to `connected` when backend/SIP confirms.
- If using SIP.js (WebRTC): `TelephonyCore` already sets `setCallState('connected')` on `SessionState.Established`. Ensure agent screen syncs with `Alpine.store('call').state` or listens to same source.

### 1.2 Wire Hangup to TelephonyCore

**Problem:** Agent `hangup()` sets `callState = 'wrapup'` but does not call `TelephonyCore.hangup()`. The SIP session stays active; Asterisk leg is not terminated from the browser.

**Fix:**
- In `agentScreen.hangup()`: Call `Alpine.store('call').hangupWebRTC()` (which calls `TelephonyCore.hangup()`) before or in addition to setting `wrapup`.
- `TelephonyCore.hangup()` already posts to `POST /api/call/hangup` and sets state.

```javascript
// agent/index.blade.php - hangup()
async hangup() {
    await Alpine.store('call').hangupWebRTC();  // SIP bye + API
    clearInterval(this.timer);
    Alpine.store('call').stopTimer();
    this.callState = 'wrapup';
    // ... rest
}
```

### 1.3 Wire Mute/Hold to TelephonyCore (When WebRTC Active)

**Current:** `toggleMute()` and `toggleHold()` already delegate to `Alpine.store('call').toggleMuteWebRTC()` and `toggleHoldWebRTC()`.

**Fix:** Ensure when **not** using WebRTC (e.g. VICIdial softphone, external dial), these buttons are hidden or disabled. When WebRTC is active, they should work. Add guard: `if (window.TelephonyCore?.hasActiveCall?.())` before invoking hold/mute.

### 1.4 Sync Agent Screen with Global Call Store

**Problem:** Agent screen has its own `callState`, `sessionId`, `phoneNumber`, `duration` while layout/global script uses `Alpine.store('call')`. Risk of desync.

**Fix:**
- Prefer **single source of truth**: `Alpine.store('call')`.
- Agent screen should `$watch` or bind to `Alpine.store('call')` for `state`, `sessionId`, `number`, `duration`.
- On init, copy from store if rehydrated.
- When dial/hangup/saveDisposition, update store and derive local display from it.

---

## Phase 2 – Agent Controller & Agent Screen Fields

### 2.1 Pass AgentScreenField to Agent View

**Problem:** `AgentController` does not pass `fields`. View expects `$fields` with `field_name`, `field_type`, `label`, `options_array`, `required`.

**Current schema (AgentScreenField):** `field_key`, `field_label`, `field_order`, `field_width`.

**Fix:**
- Load `AgentScreenField::forCampaign($campaign)->ordered()->get()`.
- Map to view format:
  - `field_name` ← `field_key`
  - `label` ← `field_label`
  - `field_type` ← `'text'` (default) or add `field_type` to migration if needed
  - `options_array` ← `[]` or from a new column
  - `required` ← `false` or new column
- Pass as `'fields' => $fields`.
- If no `field_type` in DB, default to `text` for now.

### 2.2 Add data-user-id for Echo

**Problem:** Agent screen `init()` checks `this.$el.dataset.userId` for Echo subscription, but root div has no `data-user-id`.

**Fix:**
- Add `data-user-id="{{ auth()->id() }}"` to the root `div` in `agent/index.blade.php`.

### 2.3 Implement Form Save (Capture Details)

**Problem:** `saveForm()` only shows a toast; it does not persist data to backend.

**Fix:**
- Create `POST /api/agent/capture` (or reuse a generic form submit endpoint).
- Payload: `{ campaign_code, call_session_id?, lead_id?, fields: { field_key: value } }`.
- Backend: Validate fields against AgentScreenField config, insert into form data table or `crm_call_history` / a `lead_capture` table.
- Wire `saveForm()` to collect form data and POST to this endpoint.
- On success: toast, optionally clear form; optionally auto-load next lead.

---

## Phase 3 – Lead Hopper (Predictive Dialing)

### 3.1 Data Source Options

**Option A – VICIdial Hopper**  
VICIdial has a hopper per campaign. Use VICIdial Agent API to:
- `get_next_lead` or equivalent (if supported by your VICI version).
- Agent clicks "Next Lead" → CRM calls VICIdial API → returns next lead (phone, lead_id, etc.).
- Display in agent screen, ready for dial.

**Option B – Local Lead Queue**  
- New table: `lead_hopper` (campaign_code, lead_id, phone_number, data JSON, status, assigned_at, user_id).
- API: `GET /api/leads/next` → returns next unassigned lead for campaign, marks as assigned to current user.
- After disposition: optionally `POST /api/leads/release` or update status so lead can be recycled or marked done.
- Import job or manual upload populates hopper.

**Option C – Hybrid**  
- Prefer VICIdial when available.
- Fallback to local `lead_hopper` for campaigns not using VICIdial.

### 3.2 API: GET /api/leads/next

**Response:**
```json
{
  "success": true,
  "lead": {
    "lead_id": "12345",
    "phone_number": "+639171234567",
    "client_name": "Juan Dela Cruz",
    "custom_fields": {}
  }
}
```

- If no lead: `{ "success": true, "lead": null }`.
- Throttle: 1 request per 2 seconds per user to avoid abuse.

### 3.3 UI: "Next Lead" Button

- Add "Next Lead" button on agent screen.
- On click: call `GET /api/leads/next`, populate `phoneNumber`, `leadId`, `clientName`.
- If no lead: toast "No leads available in hopper."

### 3.4 Auto-Load After Disposition (Optional)

- After `saveDisposition()` succeeds, optionally call `GET /api/leads/next` and auto-fill for next call.
- Make this configurable (setting or feature flag) to avoid surprising agents.

---

## Phase 4 – Reverb / Echo Reliability

### 4.1 Ensure Reverb Config

- `BROADCAST_CONNECTION=reverb`
- `REVERB_*` vars correct.
- Run `php artisan reverb:start` (or via supervisor).
- If Reverb fails, Echo will not connect; add fallback polling every 15–30s for `/api/call/status`.

### 4.2 Fallback Polling

- In agent screen `init()`, if `!TelephonyEcho.isBroadcastEnabled()` or subscription fails, use `setInterval(() => this.syncCallStatus(), 15000)`.
- `syncCallStatus()` already exists; ensure it updates `callState`, `sessionId`, `phoneNumber`, `dialBlocked` from response.

---

## Phase 5 – Configuration: SIP vs PJSIP

**User requirement:** "Configuration must be SIP and not PJSIP."

- `config/asterisk.php`: `agent_channel` defaults to `SIP` (chan_sip).
- Ensure `.env` has `ASTERISK_AGENT_CHANNEL=SIP` (not PJSIP).
- `AsteriskAmiService::originateWebRtc()` uses `config('asterisk.agent_channel', 'SIP')` – so it will use `SIP/{extension}`.
- If agents use WebRTC (SIP.js), Asterisk must have **PJSIP** endpoints for WebRTC. The user may mean: in the **Laravel configuration UI** (e.g. dropdown) show "SIP" as the channel type for agent routing, while Asterisk internally uses PJSIP for WebRTC. Clarify: does the user want chan_sip for agents or just the label "SIP" in config? For WebRTC, PJSIP is typically required. Keep `agent_channel` configurable; document that `SIP` = chan_sip (hardphones), `PJSIP` = WebRTC (browser).

---

## Phase 6 – Error Handling & UX

### 6.1 Clear Error Messages

- Map backend error codes to user-friendly messages (already partially in `CallErrors`).
- Display in toast on dial failure, disposition failure.

### 6.2 Disposition Required Before Next Call

- Already implemented: `dialBlocked` when `disposition_pending`.
- Ensure UI clearly shows "Save disposition to continue" when blocked.

### 6.3 No SIP Credentials / No Extension

- If user has no `extension` or `/api/sip/credentials` returns 403/empty, show info message: "SIP not configured. Contact administrator."
- Optionally hide WebRTC-specific controls (mute, hold) when SIP is not configured.

---

## Implementation Order

| # | Task | Files to Modify | Est. |
|---|------|-----------------|------|
| 1 | Fix dial() state flow (ringing → wait for broadcast/SIP) | `agent/index.blade.php` | 30 min |
| 2 | Wire hangup to TelephonyCore.hangup() | `agent/index.blade.php` | 15 min |
| 3 | Pass AgentScreenField + data-user-id | `AgentController.php`, `agent/index.blade.php` | 30 min |
| 4 | Map AgentScreenField to view schema | `AgentController.php` or helper | 20 min |
| 5 | Implement form save API + frontend | `routes`, new controller, `agent/index.blade.php` | 1 hr |
| 6 | Add GET /api/leads/next + backend logic | New controller, service, migrations (if local) | 1–2 hr |
| 7 | Add "Next Lead" button + auto-load optional | `agent/index.blade.php` | 30 min |
| 8 | Fallback polling when Echo disabled | `agent/index.blade.php` | 15 min |
| 9 | Sync agent state with Alpine.store('call') | `agent/index.blade.php` | 30 min |

---

## Validation Checklist

- [ ] Dial sets `ringing` until SIP/Reverb confirms `connected`.
- [ ] Hangup invokes TelephonyCore.hangup() and SIP session ends.
- [ ] Mute/Hold work when WebRTC active.
- [ ] Agent screen fields load and render from AgentScreenField.
- [ ] Form save persists to backend.
- [ ] "Next Lead" fetches and populates lead.
- [ ] Disposition blocks next dial until saved.
- [ ] Page navigation does not drop active call (TelephonyCore persists).
- [ ] Reverb broadcasts received; or polling keeps UI in sync.

---

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| VICIdial API may not have get_next_lead | Implement local `lead_hopper` table as fallback |
| SIP.js fails when credentials missing | Graceful message; hide WebRTC controls |
| Reverb not running in production | Fallback polling; document in INSTALLATION.md |

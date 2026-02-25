# Phase 1 – Telephony System Discovery Audit

**Date:** 2026-02-23  
**Scope:** Call state, disposition, Asterisk/VICIdial integration, agent UI, supervisor dashboard.  
**No code was modified; audit only.**

---

## 1. Located Components

### 1.1 Controllers (Telephony / Disposition)

| File | Purpose |
|------|--------|
| `app/Http/Controllers/Api/VicidialProxyController.php` | Single `__invoke`: forwards GET query (`action`, `campaign`, `value`, `phone_code`, `phone_number`) to `VicidialProxyService`. No call record created, no state stored. |
| `app/Http/Controllers/Api/SaveDispositionController.php` | Validates `campaign_code`, `disposition_code`, `disposition_label` (all required), calls `DispositionService::saveDisposition()`. Returns 422 on validation failure. |
| `app/Http/Controllers/Api/DispositionController.php` | Returns disposition codes for campaign via `DispositionService::getCodesForCampaign()` (JSON). |
| `app/Http/Controllers/AgentController.php` | Renders `agent.index` with `campaign`, `campaignName`, `user`. **Does not pass `dispositionCodes`.** |
| `app/Http/Controllers/Api/SupervisorAgentsController.php` | Returns agents (from User + AttendanceLog). Status = `available` if latest log is `login`, else `offline`. `calls_today`, `avg_handle`, `dispositions`, `current_call` are **hardcoded 0 / null**. |

### 1.2 Services

| File | Purpose |
|------|--------|
| `app/Services/Telephony/VicidialProxyService.php` | Builds VICIdial Agent API URL (user, pass, agent_user, source, **function** = action, value). For `external_dial` appends `phone_code`, `phone_number`. HTTP GET only; no Laravel state, no call ID. |
| `app/Services/Telephony/AsteriskAmiService.php` | PAMI client: `originate(channel, number, callerId)` only. No event listening, no CDR, no hangup handling. |
| `app/Services/DispositionService.php` | `getCodesForCampaign()` → DispositionRepository. `saveDisposition()` → **only** creates `CampaignDispositionRecord`. Does not: validate code against `disposition_codes`, fire `DispositionSaved`, update any call record, or write to VICIdial. |
| `app/Services/CallHistoryService.php` | `logFormSubmission()` writes to `crm_call_history` (form submission log with status e.g. RECORDED). **Not used for live call state.** `getUnifiedHistory()` / `getHistoryForCampaign()` read from `crm_call_history`. |

### 1.3 Events & Listeners

| Event | Listener | Dispatched from |
|-------|----------|-----------------|
| `CallOriginated` | `LogCallOriginated` (telephony channel log only) | **Never dispatched** in codebase. |
| `DispositionSaved` | `LogDispositionSaved` (audit channel log only) | **Never dispatched**; `DispositionService::saveDisposition()` does not fire it. |

### 1.4 Jobs

| Job | Purpose |
|-----|--------|
| `App\Jobs\AsteriskOriginateJob` | Dispatches to `AsteriskAmiService::originate()`. Queue: `asterisk`. **Not invoked by VicidialProxyController** (proxy uses HTTP to VICIdial, not AMI). |

### 1.5 Models & DB Tables

| Model | Table | Role |
|-------|--------|------|
| `AgentCallRecord` | `agent_call_records` | Fields: lead_id, phone_number, campaign_code, agent, disposition_code, disposition_label, remarks, call_duration_seconds, lead_data_json, called_at. **No status column. Never written by current dial/disposition flow.** |
| `CrmCallHistory` | `crm_call_history` | Form submission log: lead_id, phone_number, campaign_code, form_type, record_id, agent, status (e.g. RECORDED), remarks. **Not used for live call state.** |
| `CampaignDispositionRecord` | `campaign_disposition_records` | Written by `DispositionService::saveDisposition()`. No link to `agent_call_records` or any active call. |
| `DispositionCode` | `disposition_codes` | campaign_code, code, label, is_active, sort_order. Used for API list and admin CRUD; **not validated on save** in DispositionService. |
| `VicidialServer` | `vicidial_servers` | Campaign-specific API/db config. Used by VicidialProxyService to resolve server per campaign. |

### 1.6 Webhooks / AMI / CDR

- **No webhook routes** for Asterisk or VICIdial (e.g. hangup, CDR, channel state).
- **No AMI event listener** (no persistent connection or worker subscribing to AMI events).
- **No CDR parsing or reconciliation** in Laravel.

### 1.7 Broadcasting

- **No Broadcast** / **ShouldBroadcast** usage. No WebSocket/Pusher events for call or agent state.

### 1.8 Frontend (Call State & Disposition)

- **Global call store** (`resources/js/app.js`): `state` (idle | ringing | connected | hold | wrapup), `number`, `duration`, timer. **100% client-side;** no server authority.
- **Agent screen** (`resources/views/agent/index.blade.php`): `dial()` calls `GET /api/vicidial/proxy` with params `action: 'originate', phone, lead_id`. **Backend expects `phone_number` and `value`** (VicidialProxyController passes `value`, `phone_code`, `phone_number` from query). So **phone number is never sent** (sent as `phone`).
- **VICIdial Agent API** typically uses **`external_dial`** for outbound; frontend sends **`action: 'originate'`** (may not match server-side function name).
- **hangup()**: Only clears timer and sets local state to `wrapup`. No API call; backend never informed.
- **saveDisposition()**: POST to `/api/disposition/save` with `lead_id`, `phone_number`, `disposition_code`, `notes`. Backend **requires `disposition_label`**; frontend does not send it → **validation fails (422)**.
- **Disposition dropdown**: Rendered with `@foreach($dispositionCodes ?? [] as $dc)`. **`dispositionCodes` is never passed to agent.index** (AgentController does not pass it; no view composer for it) → **dropdown is always empty**.

---

## 2. Current Call Lifecycle (As Implemented)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           CURRENT CALL LIFECYCLE (BROKEN)                        │
└─────────────────────────────────────────────────────────────────────────────────┘

  [Agent UI]                    [Laravel]                      [VICIdial / Asterisk]
       │                             │                                    │
       │  GET /api/vicidial/proxy    │                                    │
       │  action=originate           │                                    │
       │  phone=<number>  (wrong     │  VicidialProxyService              │
       │  param name for backend)     │  → HTTP GET to VICIdial API        │
       │  lead_id=...                 │  (function=originate? or           │
       │ ──────────────────────────► │   external_dial; phone_number      │
       │                             │   from request is EMPTY)            │
       │                             │ ──────────────────────────────────►│
       │                             │                                    │
       │  state = 'ringing' (UI)     │  No call record created             │
       │  then state = 'connected'   │  No event dispatched               │
       │  (assumed on 200 OK)        │  No AMI / webhook                   │
       │                             │                                    │
       │  [User hangs up in VICIdial / Asterisk]                            │
       │  ← No event ever received by Laravel or UI                         │
       │  [Agent clicks Hangup in UI] │                                    │
       │  state = 'wrapup' (UI only) │  No API call                        │
       │                             │                                    │
       │  POST /api/disposition/save │                                    │
       │  disposition_code, notes    │  SaveDispositionController          │
       │  (no disposition_label)     │  → validation FAILS (422)          │
       │ ──────────────────────────► │  disposition_label required         │
       │                             │                                    │
       │  [If label were sent:]      │  DispositionService                 │
       │                             │  → CampaignDispositionRecord::create│
       │                             │  (no DispositionSaved event,        │
       │                             │   no code validation, no VICIdial   │
       │                             │   write-back, no link to call)      │
       │                             │                                    │
       │  state = 'idle' (after      │                                    │
       │  save or manual reset)      │                                    │
       │                             │                                    │
└─────────────────────────────────────────────────────────────────────────────────┘

  SUPERVISOR DASHBOARD:
  - Agent status = attendance only (login = available, else offline).
  - calls_today, avg_handle, dispositions, current_call = 0 / null (hardcoded).
  - No real-time call or disposition data.
```

---

## 3. Database Tables Involved (Summary)

| Table | Used for call state? | Used for disposition? | Notes |
|-------|----------------------|------------------------|-------|
| `agent_call_records` | No (never written in flow) | No | Has disposition_code default 'OTHER'; no status column. |
| `crm_call_history` | No | No | Form submission log only (status = RECORDED etc). |
| `campaign_disposition_records` | No | Yes | Only table written on “save disposition”; no call_id/link. |
| `disposition_codes` | No | Yes (list only) | Not validated on save. |
| `vicidial_servers` | Yes (resolve API URL) | No | Per-campaign. |
| `attendance_logs` | Indirect (agent “online”) | No | Supervisor status = latest event_type. |

**No table holds live call state** (e.g. ringing, answered, in_call, completed). No unique call identifier (e.g. LinkedID, channel id) stored in Laravel.

---

## 4. Identified Inconsistencies & Gaps

### 4.1 Call state unreliable

- **Cause:** Call state exists only in the browser (`Alpine.store('call')` and agent screen component). Backend never creates or updates a call record on dial/hangup.
- **Hangup:** Backend has no AMI listener and no webhook. When the call ends in Asterisk/VICIdial, Laravel and the UI are never notified → calls can stay “connected” in UI indefinitely.
- **Stuck “active” calls:** No server-side state to correct; UI can show connected until user refreshes or clicks Hangup (which only updates local state).

### 4.2 Disposition not functioning properly

- **Some dispositions not saving:** Frontend sends `notes` but not `disposition_label`; backend validation requires `disposition_label` → **all saves fail with 422** unless label is sent.
- **Not mapped correctly:** DispositionService does not validate that `disposition_code` exists in `disposition_codes` for the campaign; no mapping to VICIdial status.
- **VICIdial disposition mismatch:** No write-back to VICIdial; no sync of Laravel disposition to VICIdial.
- **UI does not enforce required disposition:** Disposition block is shown in wrapup/idle but dropdown is empty (`dispositionCodes` not passed to agent view). No “block next call until disposition saved” logic.
- **Call log does not reflect final status:** No single “call” record updated with final disposition. `CampaignDispositionRecord` is standalone; `agent_call_records` and `crm_call_history` are not updated with disposition.

### 4.3 Missing state transitions

- No server-side states (idle, dialing, ringing, answered, in_call, on_hold, completed, failed, abandoned).
- No transitions defined; no timeouts or forced correction.
- No “completed” or “failed” from backend; UI assumes success on 200 from proxy.

### 4.4 Race conditions / double updates

- No central call record to update → no DB race on state.
- Duplicate disposition submissions are possible (no idempotency key or “already dispositioned” check per call).

### 4.5 Dead / ineffective endpoints and code

- **CallOriginated:** Never dispatched; dead for telephony flow.
- **DispositionSaved:** Never dispatched from DispositionService.
- **AsteriskOriginateJob:** Not used by the proxy (proxy uses VICIdial HTTP API). AMI originate path is unused in current dial flow.
- **VicidialProxyController** param mismatch: frontend sends `phone`, `lead_id`; controller passes `value`, `phone_code`, `phone_number` → `phone_number` is always empty on backend.

### 4.6 Supervisor dashboard

- Does not reflect real-time call state; no data from calls or dispositions; all metrics hardcoded to 0/null.

---

## 5. Flow Diagram (Text)

```
DIAL FLOW (current, broken):
  Agent UI (dial) 
    → GET /api/vicidial/proxy?action=originate&phone=X&lead_id=Y
    → VicidialProxyController (action, value='', phone_code='1', phone_number='')
    → VicidialProxyService::execute() 
    → HTTP GET to VICIdial (phone_number empty; function=originate may be wrong)
    → UI sets state to ringing then connected (no backend state)
  No Laravel call record. No CallOriginated event. No AMI used in this path.

HANGUP:
  Agent UI (hangup) → local state = wrapup. No API. No backend update.
  Actual hangup in Asterisk/VICIdial → no webhook/AMI → Laravel never knows.

DISPOSITION SAVE (current, broken):
  Agent UI (saveDisposition) 
    → POST /api/disposition/save { campaign_code, lead_id, phone_number, disposition_code, notes }
    → SaveDispositionController (validates disposition_label required → 422)
  If label were sent:
    → DispositionService::saveDisposition()
    → CampaignDispositionRecord::create() only
    → No DispositionSaved event, no code validation, no VICIdial sync, no link to call.

SUPERVISOR:
  GET /api/supervisor/agents 
    → SupervisorAgentsController 
    → User + AttendanceLog → status = available/offline; calls_today/current_call = 0/null.
```

---

## 6. Summary Table

| Area | Finding |
|------|--------|
| **Call state** | Client-only; no DB or backend state; no hangup detection. |
| **Dial API** | Param mismatch (phone vs phone_number); possible wrong action (originate vs external_dial). |
| **Disposition save** | Fails validation (missing disposition_label); no validation against disposition_codes; no event; no VICIdial sync; no link to call. |
| **Disposition UI** | Dropdown empty (dispositionCodes not passed to agent view). |
| **Events** | CallOriginated and DispositionSaved never fired. |
| **AMI** | Used only by AsteriskOriginateJob; not used in current dial path; no event listener. |
| **Webhooks** | None. |
| **Broadcasting** | None. |
| **Supervisor** | No real-time call or disposition data. |
| **Tables** | No table stores live call state or unique call id; agent_call_records unused in flow. |

---

**Next:** Phase 2 – Telephony state re-architecture (CallStateService, state machine, atomic updates, idempotent handlers).

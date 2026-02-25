# Phase 4 – Disposition Code Rebuild (Complete)

**Date:** 2026-02-24  
**Status:** Implemented

---

## Summary

Rebuilt the disposition flow: validate against allowed campaign codes, enforce disposition required after call end, block next dial until disposition saved, link disposition to call session, prevent duplicates, update CallSession atomically, and sync to VICIdial when possible.

---

## Implemented

### 1. Schema & Model

- **call_session_id** on `campaign_disposition_records` with unique constraint (one disposition per call)
- `CampaignDispositionRecord.call_session_id` fillable

### 2. DispositionService

- **Validate code**: Resolves disposition against `disposition_codes` for campaign; rejects invalid/inactive
- **Link to CallSession**: Accepts `call_session_id`; when present, validates user owns session, call is terminal, no prior disposition
- **Atomic save**: DB transaction; creates `CampaignDispositionRecord`, updates `CallSession` disposition fields, syncs to VICIdial
- **Duplicate prevention**: Checks `CallSession.disposition_code` and `CampaignDispositionRecord` existence
- **Fires DispositionSaved** event
- **hasPendingDisposition(userId)**: True when user has terminal call without disposition
- **getPendingDispositionSession(userId)**: Returns the session needing disposition

### 3. CallOrchestrationService

- **Block dial** when `hasPendingDisposition()` is true; returns "Please save disposition for your last call before making a new one."
- **getPendingDispositionSession()** for status API

### 4. VicidialDispositionSyncService

- Attempts lead status update in VICIdial via Non-Agent API (`update_lead`)
- Uses `lead_id` when available; maps Laravel codes via `config('vicidial.disposition_map')`
- Fails gracefully with logging; does not throw

### 5. API & UI

- **SaveDispositionController**: Accepts `call_session_id`; passes `userId`
- **CallController::status()**: Returns `disposition_pending` and `pending_call` when applicable
- **Agent screen**: Stores `sessionId` from dial; sends it in disposition save; polls status on init; blocks dial when `dialBlocked`
- **Layout disposition modal**: Uses `Alpine.store('call').sessionId`; passes `campaign_code`, `call_session_id`, `phone_number`
- **Click-to-call**: Stores `session_id` in call store
- **View composer**: Injects `dispositionCodes` into layout for modal

### 6. Config

- `config/vicidial.php`: `disposition_map` for Laravel → VICIdial code mapping
- `data-campaign` on body for layout modal

---

## Modified Files

| File | Change |
|------|--------|
| `app/Services/DispositionService.php` | Validation, call session link, atomic save, Vicidial sync, pending checks |
| `app/Services/Telephony/CallOrchestrationService.php` | Block dial when pending disposition; getPendingDispositionSession |
| `app/Http/Controllers/Api/SaveDispositionController.php` | Accept call_session_id; pass userId |
| `app/Http/Controllers/Api/CallController.php` | status() returns disposition_pending, pending_call |
| `app/Models/CampaignDispositionRecord.php` | call_session_id fillable |
| `app/Providers/AppServiceProvider.php` | dispositionCodes in layout composer |
| `config/vicidial.php` | disposition_map |
| `resources/views/agent/index.blade.php` | sessionId, syncCallStatus, dialBlocked, call_session_id in save |
| `resources/views/layouts/app.blade.php` | data-campaign on body |
| `resources/js/app.js` | sessionId in call store |
| `resources/js/components.js` | clickToCall stores sessionId; dispositionModal passes session_id, campaign |
| `tests/Unit/Services/DispositionServiceTest.php` | VicidialDispositionSyncService in constructor |
| `tests/Feature/DispositionSaveTest.php` | Create DispositionCode before save |

---

## Created Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_24_000003_add_call_session_to_disposition.php` | call_session_id + unique |
| `app/Services/Telephony/VicidialDispositionSyncService.php` | VICIdial lead status write-back |

---

## Flow

1. Agent dials → `session_id` returned and stored in Alpine store and agent state.
2. Agent hangs up → state moves to wrapup; layout modal or agent disposition form shown.
3. Agent saves disposition → `call_session_id` sent; backend validates code, updates `CallSession`, creates `CampaignDispositionRecord`, attempts VICIdial sync.
4. Agent tries to dial again without disposition → blocked with clear message.
5. Status endpoint (`/api/call/status`) returns `disposition_pending: true` and `pending_call` when applicable; agent UI shows wrapup and blocks dial.

---

## Next Steps (Phase 5)

- Real-time broadcasting (WebSockets/Pusher) for call state and agent state

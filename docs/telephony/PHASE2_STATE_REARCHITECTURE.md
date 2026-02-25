# Phase 2 – Telephony State Re-Architecture (Complete)

**Date:** 2026-02-24  
**Status:** Implemented

---

## Summary

Implemented a central call state machine with `CallStateService`, `CallSession` model, event-driven updates, atomic DB operations, and reconciliation. Dial flow now creates call sessions; hangup API and AMI webhook update state; supervisor dashboard uses real call data.

---

## State Machine

| States | Description |
|--------|-------------|
| `dialing` | Call originated, awaiting ring |
| `ringing` | Outbound ringing |
| `answered` | Call answered |
| `in_call` | Active conversation |
| `on_hold` | On hold |
| `transferring` | Transfer in progress |
| `completed` | Normal end |
| `failed` | Origination or system failure |
| `abandoned` | Ring timeout, no answer |

**Valid transitions** (see `CallStateService::VALID_TRANSITIONS`):
- dialing → ringing, failed, abandoned
- ringing → answered, failed, abandoned
- answered → in_call, failed
- in_call → on_hold, transferring, completed, failed
- on_hold → in_call, failed
- transferring → in_call, completed, failed

**Force correction** (reconciliation): Any active state → failed | abandoned

---

## Modified Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/VicidialProxyController.php` | Delegates dial (originate/external_dial) to CallOrchestrationService; accepts `phone` query param |
| `app/Http/Controllers/Api/SaveDispositionController.php` | `disposition_label` optional; resolved from DispositionCode; accepts `notes` |
| `app/Http/Controllers/AgentController.php` | Passes `dispositionCodes` to agent view |
| `app/Http/Controllers/Api/SupervisorAgentsController.php` | Uses `call_sessions` and `campaign_disposition_records` for real data |
| `app/Providers/EventServiceProvider.php` | Registered `CallStateChanged` → `LogCallStateChanged` |
| `bootstrap/app.php` | CSRF exempt for `api/webhooks/ami` |
| `config/asterisk.php` | Added `webhook_secret` |
| `routes/web.php` | Added `api/call/dial`, `api/call/hangup`, `api/call/status`; `api/webhooks/ami` (no auth) |
| `routes/console.php` | Scheduled `ReconcileCallStateJob` every 15 minutes |
| `resources/views/agent/index.blade.php` | Calls hangup API; sends campaign_code, call_duration_seconds in disposition |
| `resources/js/components.js` | clickToCall hangup calls API |

---

## Created Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_24_000001_create_call_sessions_table.php` | `call_sessions` table |
| `app/Models/CallSession.php` | Call session model with status constants |
| `app/Services/Telephony/CallStateService.php` | State machine, transitions, force correction |
| `app/Services/Telephony/CallOrchestrationService.php` | Orchestrates dial + hangup with VICIdial |
| `app/Events/CallStateChanged.php` | Dispatched on state transition |
| `app/Listeners/LogCallStateChanged.php` | Logs to telephony channel |
| `app/Http/Controllers/Api/CallController.php` | dial, hangup, status endpoints |
| `app/Http/Controllers/Api/AmiWebhookController.php` | Idempotent AMI webhook (Hangup events) |
| `app/Jobs/ReconcileCallStateJob.php` | Forces stale active calls to failed (2h threshold) |

---

## New API Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/call/dial` | Yes | Start outbound call (creates session) |
| POST | `/api/call/hangup` | Yes | Hang up active call |
| GET | `/api/call/status` | Yes | Get agent's active call |
| POST | `/api/webhooks/ami` | No (X-Webhook-Secret optional) | AMI event webhook |

---

## Backward Compatibility

- `GET /api/vicidial/proxy?action=originate&phone=X&lead_id=Y` still works; now delegates to CallOrchestrationService and creates call sessions.
- Existing disposition save flow fixed: `disposition_label` is optional and resolved from `disposition_codes`.

---

## Configuration

```env
# Optional: require for AMI webhook (recommended in production)
ASTERISK_AMI_WEBHOOK_SECRET=your_secret_here
```

---

## Next Steps (Phase 3)

- AMI event mapping (LinkedID, channel) to call sessions
- Reconciliation against Asterisk CDR
- Unified call UUID mapping layer

# Phase 3 – Asterisk / VICIdial Synchronization (Complete)

**Date:** 2026-02-24  
**Status:** Implemented

---

## Summary

Implemented a unified call UUID mapping layer, LinkedID tracking, extension-based fallback matching, and reconciliation of unmatched AMI events. Backend no longer depends solely on frontend state; AMI webhook can correlate hangup events to call sessions via linkedid, channel, or agent extension.

---

## Implemented Components

### 1. CallUuidMappingService

- **Extract extension from channel**: Parses SIP/PJSIP/Local channel format (e.g. `SIP/1001-00000001` → `1001`)
- **Find user by extension**: Uses `User.extension` or falls back to `vici_user`
- **Find session for hangup**: Tries linkedid → channel → extension fallback (user's most recent active call)
- **Attach Asterisk identifiers**: Stores linkedid/channel on session for future events
- **Log unmatched**: Writes to `unmatched_ami_events` for reconciliation

### 2. AMI Webhook Enhancements

- Uses `CallUuidMappingService` for all lookup
- Supports `Linkedid` and `Uniqueid` in payload (Asterisk uses different casing)
- Fallback matching by extension when linkedid/channel not found
- Logs unmatched hangups for retry
- Idempotent: safe to process duplicate events

### 3. Unmatched Event Reconciliation

- `unmatched_ami_events` table stores events we couldn't match
- `ReconcileCallStateJob` retries matching (e.g. session created after webhook)
- Processes events from last 2 hours, max 100 per run
- Cleans processed events older than 7 days

### 4. User Extension Mapping

- Added `extension` column to users (nullable)
- Falls back to `vici_user` when extension is null (common when they match)
- Admin API supports extension in create/update

### 5. VICIdial Proxy

- For `external_dial`, appends `phone_number` to URL when provided (compatibility)

---

## Modified Files

| File | Change |
|------|--------|
| `app/Http/Controllers/Api/AmiWebhookController.php` | Uses CallUuidMappingService; fallback matching; logs unmatched |
| `app/Jobs/ReconcileCallStateJob.php` | Retries unmatched AMI events; marks processed |
| `app/Services/Telephony/VicidialProxyService.php` | Adds phone_number param for external_dial |
| `app/Models/User.php` | Added extension to fillable |
| `app/Services/UserService.php` | Handles extension in create/update |
| `app/Http/Requests/Admin/StoreUserRequest.php` | extension validation |
| `app/Http/Requests/Admin/UpdateUserRequest.php` | extension validation |
| `.env.example` | ASTERISK_AMI_WEBHOOK_SECRET |

---

## Created Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_02_24_000002_add_telephony_sync_fields.php` | extension on users; unmatched_ami_events table |
| `app/Models/UnmatchedAmiEvent.php` | Model for unmatched events |
| `app/Services/Telephony/CallUuidMappingService.php` | UUID/extension mapping, session lookup |

---

## Configuration

```env
ASTERISK_AMI_WEBHOOK_SECRET=your_secret  # Required in production; webhook validates X-Webhook-Secret header
```

**User.extension**: Set via admin API or DB. When null, `vici_user` is used for extension matching. Typical: extension equals VICIdial agent ID (e.g. SIP extension 1001).

---

## AMI Webhook Payload (Expected)

```json
{
  "Event": "Hangup",
  "Channel": "SIP/1001-00000001",
  "Linkedid": "1234567890.12",
  "Uniqueid": "1234567890.12"
}
```

Asterisk may send different casing (`Event` vs `event`). Both are supported.

---

## Next Steps (Phase 4)

- Disposition code rebuild: enforce required after call end, VICIdial write-back, lock call until disposition

# Phase 5 – Real-Time Event System

## Summary

Implemented a **Pusher/Reverb-compatible** real-time broadcasting layer for telephony state and disposition events. The system uses Laravel Echo on the frontend with optional fallback polling.

## What Was Implemented

### 1. Broadcastable Events

- **CallStateChanged** – Implements `ShouldBroadcast`, broadcasts to:
  - `private-App.Models.User.{userId}` – Agent’s own channel
  - `private-telephony.supervisor` – Supervisor dashboard channel
- **DispositionSaved** – Implements `ShouldBroadcast`, broadcasts to:
  - `private-telephony.supervisor`

### 2. Channels (`routes/channels.php`)

- `App.Models.User.{id}` – Authenticated user can only subscribe to their own channel
- `telephony.supervisor` – Users with role Super Admin, Admin, or Team Leader

### 3. Echo Bootstrap (`resources/js/echo.js`)

- Conditionally initializes Echo when `VITE_REVERB_APP_KEY` or `VITE_PUSHER_APP_KEY` is set
- Supports Reverb (self-hosted) and Pusher
- `subscribeAgentChannel(userId, onCallStateChanged)` – Agent call state updates
- `subscribeSupervisorChannel(onCallStateChanged, onDispositionSaved)` – Supervisor telephony updates

### 4. Agent Screen Integration

- Subscribes to `App.Models.User.{id}` for `call.state.changed`
- On hangup/completed/failed/abandoned: transition to wrapup
- Fallback: 30s poll to `/api/call/status` when broadcast is disabled

### 5. Supervisor Dashboard Integration

- Subscribes to `telephony.supervisor` for `call.state.changed` and `disposition.saved`
- On any event: triggers `refresh()` (full agents/stats refetch)
- With broadcast: 60s fallback poll; without: 15s poll

### 6. Configuration

**`.env.example`** – Broadcasting variables for Reverb/Pusher.

**Default:** `BROADCAST_CONNECTION=log` – Events are logged only; no WebSocket.

## Enabling Real-Time WebSockets

### Option A: Laravel Reverb (self-hosted)

1. `composer require laravel/reverb`
2. `php artisan reverb:install`
3. Add to `.env`:
   ```
   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=your-app-id
   REVERB_APP_KEY=your-app-key
   REVERB_APP_SECRET=your-app-secret
   REVERB_HOST=127.0.0.1
   REVERB_PORT=6001
   REVERB_SCHEME=http

   VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
   VITE_REVERB_HOST="${REVERB_HOST}"
   VITE_REVERB_PORT="${REVERB_PORT}"
   VITE_REVERB_SCHEME="${REVERB_SCHEME}"
   ```
4. Run Reverb: `php artisan reverb:start`
5. Run queue worker: `php artisan queue:work`

### Option B: Pusher Channels

1. `composer require pusher/pusher-php-server`
2. Add to `.env`:
   ```
   BROADCAST_CONNECTION=pusher
   PUSHER_APP_ID=...
   PUSHER_APP_KEY=...
   PUSHER_APP_SECRET=...
   PUSHER_APP_CLUSTER=mt1

   VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
   VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
   ```
3. Run queue worker: `php artisan queue:work`

## Event Payloads

### call.state.changed

```json
{
  "session_id": 1,
  "user_id": 5,
  "from_status": "in_call",
  "to_status": "completed",
  "phone_number": "+639123456789",
  "campaign_code": "mbsales",
  "answered_at": "2026-02-24T10:15:00+00:00",
  "ended_at": "2026-02-24T10:17:30+00:00",
  "timestamp": "2026-02-24T10:17:30+00:00"
}
```

### disposition.saved

```json
{
  "agent": "John Doe",
  "campaign_code": "mbsales",
  "disposition_code": "SALE",
  "timestamp": "2026-02-24T10:18:00+00:00"
}
```

## Reconnection and Deduplication

- Laravel Echo handles reconnection automatically
- Event payload includes `timestamp` and `session_id` for client-side deduplication
- Fallback polling ensures updates even if WebSocket is temporarily unavailable

## Files Modified/Created

| File | Action |
|------|--------|
| `app/Events/CallStateChanged.php` | Modified – `ShouldBroadcast`, channels, payload |
| `app/Events/DispositionSaved.php` | Modified – `ShouldBroadcast` to supervisor |
| `routes/channels.php` | Modified – added `telephony.supervisor` |
| `resources/js/echo.js` | Created |
| `resources/js/app.js` | Modified – import echo |
| `resources/views/agent/index.blade.php` | Modified – Echo subscription, fallback poll |
| `resources/views/admin/supervisor.blade.php` | Modified – Echo subscription, fallback poll |
| `package.json` | Modified – laravel-echo, pusher-js |
| `.env.example` | Modified – broadcast vars |

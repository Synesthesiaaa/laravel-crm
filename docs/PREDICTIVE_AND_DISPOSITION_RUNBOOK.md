# Predictive dialing, hopper, and inbound disposition runbook

## Environment flags

| Variable | Default (`.env.example`) | Purpose |
|----------|-------------------------|---------|
| `HOPPER_AUTO_TOPUP_ENABLED` | `true` | Minute scheduler loads `NEW` / due `CALLBK` leads into `lead_hopper` for campaigns with `predictive_enabled`. |
| `VICIDIAL_DISPO_INBOUND_ENABLED` | `false` | When `true`, `dispo_set` events on `/api/webhooks/vicidial-events` update `leads.status`, complete hopper rows, and insert `agent_call_dispositions` (`vicidial_webhook`). |
| `VICIDIAL_DISPO_POLL_ENABLED` | `false` | When `true`, scheduler runs `ReconcileVicidialLeadStatusJob` (requires `vicidial_servers` **DB** host/user/pass/name) to read `vicidial_list.modify_date` and reconcile CRM leads. |
| `AGENT_UNIFIED_SAVE_ENABLED` | `false` | When `true`, the agent screen uses one POST (`/api/agent/record/save`) for capture + disposition; when `false`, legacy `/api/agent/capture` + `/api/disposition/save` remain. |

## Pausing hopper top-up

Set `HOPPER_AUTO_TOPUP_ENABLED=false` and deploy, or stop `php artisan schedule:run` for the CRM host. Existing hopper rows are unchanged.

## Replaying a missed webhook

Re-POST the same `dispo_set` payload to `/api/webhooks/vicidial-events` (with `X-Webhook-Secret` if configured). Idempotency: duplicate `vicidial_webhook` rows for the same lead/code within ~1 minute are skipped.

## Single-lead reconciliation

With DB access to ViciDial, update `vicidial_list.status` for the lead and run the poll job once (`VICIDIAL_DISPO_POLL_ENABLED=true`), or manually set `leads.status` in the CRM and clear pending hopper rows if needed.

## PHPUnit

`phpunit.xml` sets all four flags to `true` so feature tests exercise inbound sync and unified save without editing `.env`.

# Asterisk 13 VICIdial -> CRM Direct Integration Guide

This guide executes the direct integration model used by this CRM:
- CRM controls dialing via AMI
- Agent channel is SIP-only (`SIP/{extension}`)
- Outbound leg goes to `SIP/goip-trunk/{number}`
- AMI events are pushed to `/api/webhooks/ami` through `php artisan ami:listen`

## 1) Asterisk server readiness

Run on the Asterisk host:

```bash
asterisk -rx "manager show settings"
asterisk -rx "sip show peers"
asterisk -rx "core show channels"
```

Checks:
- AMI enabled on `5038`
- GoIP peer `goip-trunk` exists/reachable
- No stuck channels before smoke tests

## 2) manager.conf requirements

Ensure the AMI user matches CRM `.env`:

```ini
[cron]
secret = STRONG_SECRET
read = system,call,log,verbose,command,agent,user,config
write = system,call,log,verbose,command,agent,user,config
permit = CRM_SERVER_IP/255.255.255.255
deny = 0.0.0.0/0.0.0.0
```

Reload:

```bash
asterisk -rx "manager reload"
```

## 3) CRM environment wiring

Set these in `.env`:

```dotenv
ASTERISK_AMI_HOST=ASTERISK_IP
ASTERISK_AMI_PORT=5038
ASTERISK_AMI_USERNAME=cron
ASTERISK_AMI_SECRET=STRONG_SECRET
ASTERISK_GOIP_TRUNK=goip-trunk
ASTERISK_AGENT_CHANNEL=SIP
BROADCAST_CONNECTION=reverb
```

Then clear cache:

```bash
php artisan config:clear
php artisan cache:clear
```

## 4) Preflight check from CRM host

Run:

```bash
php artisan telephony:preflight
```

The command validates:
- AMI env variables
- SIP-only channel setting
- required telephony routes
- TCP connectivity to `ASTERISK_AMI_HOST:ASTERISK_AMI_PORT`

## 5) Listener deployment (production)

Start manually:

```bash
php artisan ami:listen
```

For production Supervisor, use:
- `deploy/supervisor/laravel-ami-listener.conf.example`

Apply and reload Supervisor:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start laravel-ami-listener
```

## 6) Direct dial smoke test

1. Log in as an agent with valid `extension` and `sip_password`.
2. Open agent page.
3. Place call using UI (`POST /api/call/dial`).
4. Verify Asterisk:

```bash
asterisk -rx "core show channels"
asterisk -rx "sip show channels"
```

Expected:
- Agent SIP leg created
- Outbound GoIP leg created
- Bridge established on answer

CLI smoke-test alternative:

```bash
php artisan telephony:smoke-dial --user-id=AGENT_ID --number=DESTINATION --campaign=mbsales
```

## 7) Predictive dialing validation

1. Enable campaign options in Admin -> Campaigns:
   - `predictive_enabled`
   - `predictive_delay_seconds`
   - `predictive_max_attempts`
2. Ensure `lead_hopper` has pending rows for campaign.
3. Enable predictive mode from Agent UI.
4. Confirm sequence:
   - fetch next lead
   - dial
   - disposition
   - next auto-dial after configured delay

CLI enable helper:

```bash
php artisan telephony:enable-predictive mbsales --delay=3 --max-attempts=3
```

## 8) Production validation checklist

- `php artisan telephony:preflight` passes
- `php artisan route:list | rg "api/call/dial|api/call/predictive-dial|api/webhooks/ami"`
- Reverb running and connected
- AMI listener running under Supervisor
- Logs healthy:
  - `storage/logs/telephony.log`
  - `storage/logs/telephony-events.log`
  - `storage/logs/telephony-errors.log`

## 9) Rollback/safety

- Keep `goip-trunk` config unchanged in `sip.conf`
- Disable predictive per campaign if needed
- If AMI listener is down, UI fallback polling still works for status continuity


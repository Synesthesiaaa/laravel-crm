# Monitoring

Monitor the application, background work, and external services separately. A green web process cannot prove a PBX session, synchronized history, email delivery, or scheduler is healthy.

## Signals and limits

| Signal | What it tells you | What it does not prove |
| --- | --- | --- |
| `/up` | Laravel health route responds | Complete feature/integration readiness |
| `/api/telephony/health` | Local call/alert/job metrics and limited remote reachability | Authenticated successful VICIdial operations or complete PBX readiness |
| `/api/websocket/health` | Frontend connection/configuration information | Every authenticated subscription receives events |
| Horizon dashboard | Configured Redis consumers and job history | Dedicated database email worker or all non-Horizon processes |
| Email worker heartbeat | Matching consumer recently looped in shared cache | A recipient received a message |
| Call history sync status | Local synchronization progress/failure timestamps | All historical dates were backfilled |
| Supervisor page | Polling/broadcast state with source freshness | CRM login equals external phone availability |

Health/operations endpoints are public in current routes; restrict exposure according to [security](security.md). Telephony health can make an HTTP request to a configured server; do not use it as a purely offline command.

## Telephony status calculation

[TelephonyHealthService](../../app/Services/Telephony/TelephonyHealthService.php) returns `critical` when stale active calls exist, failed application jobs in its scope exceed 10 in 24 hours, or its database check fails. It returns `degraded` when unmatched hangup events exceed 20, recent alerts exceed 50, or its reachability test fails. Otherwise it returns `ok`. Some database/metric queries can still fail before a complete health response.

The reachability check uses the first active server with an API URL, treats HTTP responses below 500 as reachable, and treats no configured server as not applicable/true. Thus `vicidial_reachable=true` does not prove correct credentials, all campaign servers, or a live agent session. `/api/telephony/health` uses 503 for critical status. [Reporting documentation](../user-guide/reports.md) describes source-qualified business metrics.

## Logs and escalation

[logging.php](../../config/logging.php) defines application, audit, security, telephony, telephony-events, telephony-errors, and rate-limit channels. Application output may be `laravel.log` or dated daily files depending on `LOG_STACK`. Default daily retention: application 14 days, audit 90, security 365, telephony 30, telephony events 14, errors 30, rate limits 14. Database Activity Log cleanup is separate from file retention. Scheduler output is appended to `storage/logs/scheduler.log`; service-manager output depends on its configuration.

Track worker restarts, queue age/backlog, failure count, scheduler execution, sync freshness per server/campaign, unmatched AMI events, stale calls, and email blocked/uncertain states. Alert thresholds should reflect staging observations; the repository supplies no proven capacity target.

For an incident, collect the timestamp/timezone, release commit, affected endpoint, synthetic reproduction, role/campaign code, redacted response status, worker connection/queue, and sanitized exception context. Exclude tokens, passwords, query signatures, recipients, lead data, and PDF contents from public issues. See [troubleshooting](troubleshooting.md).

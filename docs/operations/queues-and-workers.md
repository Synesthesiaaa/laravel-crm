# Queues, workers, listeners, and scheduler

A queue stores work; a worker consumes it. A Laravel queue **connection** chooses storage (for example Redis or the database); a **queue name** chooses a lane within it. A process can be healthy while listening on the wrong connection or lane.

## Process map

| Process | Purpose | Important boundary |
| --- | --- | --- |
| Web server/PHP | Requests, forms, login, administration | Does not replace background consumers |
| `default` consumer | General jobs/broadcast work as dispatched | Uses configured connection |
| `imports` consumer | `ImportLeadsCsvJob` | Current job imports form rows, not LeadHopper records |
| `asterisk` consumer | `AsteriskOriginateJob` | Separate direct AMI job; do not assume ordinary browser dial uses it |
| `telephony` consumer | History sync and telephony work | Required for timely local history |
| `telephony-sync` consumer | Durable VICIdial disposition and lead-field outbox | Must use the `database` connection; Horizon's Redis supervisors do not consume it |
| Horizon | Redis queue management | Configured lanes: default, imports, asterisk, telephony; Linux PCNTL/POSIX required |
| Dedicated email consumer | Campaign batch and recipient delivery | Separate connection and queue, **one worker**, not covered by Horizon defaults |
| `ami:listen` | Persistent in-process AMI event stream | A listener, not a queue worker |
| `ami:events:listen` | AMI event stream forwarded to the webhook | Alternate listener; run only when its webhook forwarding path is intentionally configured |
| `reverb:start` | WebSocket server | Needs public proxy/TLS and matching app/frontend settings |
| OS scheduler | Runs Laravel schedule once a minute | Includes permanent retention deletion and email maintenance |

Sources: [queue.php](../../config/queue.php), [horizon.php](../../config/horizon.php), [jobs](../../app/Jobs), [console schedule](../../routes/console.php). See also [developer jobs](../developer/events-and-jobs.md).

## Isolated development

Start only the required processes, in separate terminals:

```bash
php artisan serve
npm run dev
```

For asynchronous development with `QUEUE_CONNECTION=database`, an operator can separately start:

```bash
php artisan queue:work database --queue=telephony,default,imports,asterisk --tries=1 --timeout=800
```

The durable remote-write outbox has a dedicated database lane:

```bash
php artisan queue:work database --queue=telephony-sync --tries=1 --timeout=60
```

The `start-dev.bat` and `start-dev.sh` profiles omit a connection for general application lanes so Laravel uses `QUEUE_CONNECTION` (Redis by default in `.env.example`). They pin only `telephony-sync` to the database connection. If `QUEUE_CONNECTION=database`, set `DB_QUEUE_RETRY_AFTER=900` so long imports are not reserved again before their 800-second worker timeout.

This explicit long timeout accommodates import jobs (600 seconds; Horizon imports supervisor timeout is 750 seconds); it is not a recommended single production worker design. Set database `retry_after` above the longest worker/job timeout before starting it (for example `DB_QUEUE_RETRY_AFTER=900` for this configuration) so reservations are not redelivered while still running. Timeouts and signals have limitations on native Windows; use WSL/Linux for production-equivalent validation. Separate production lanes prevent long imports blocking call work.

`composer run dev` starts web, the `telephony,default` listener, Pail, Vite, Reverb, and a database worker for `telephony-sync`. It does **not** start imports, the asterisk lane, AMI, the scheduler, or email. The general listener has timeouts disabled; use supervised production consumers with bounded timeouts.

## Horizon in production

Horizon explicitly uses Redis even if the application's default queue is database. Ensure dispatchers and consumers agree. The configured supervisors separate `default`, `imports`, `asterisk`, and `telephony`; imports timeout is 750 seconds and Redis `retry_after` defaults to 900. A unique shared cache is also needed for job uniqueness and overlap locks.

Use a Linux service manager to run `php artisan horizon`, restart it after crashes, and allow in-flight work to finish. `php artisan horizon:terminate` gracefully reloads Horizon under supervision after deployment. For plain workers use `php artisan queue:restart`; the process manager must restart exited workers. Do not start duplicate unmanaged consumers during diagnosis.

## User Access CSV import worker

Super Admin previews and account imports use the dedicated `user-imports` database connection and queue, regardless of `QUEUE_CONNECTION`:

```bash
php artisan queue:work user-imports --queue=user-imports --sleep=1 --tries=0 --timeout=120
```

Each reservation processes at most 50 CSV records, commits account writes/counters/cursor together, and releases itself for the next batch. The job contains only the import UUID and phase. The 360-second reservation exceeds its 120-second timeout. Unlimited reservations are intentional; validation/runtime failures terminate the import with safe diagnostics. The existing Horizon Redis `imports` supervisor does not consume this connection. Web and worker must share private local source storage. Run `users:imports-prune` through the hourly scheduler to remove abandoned sources and old safe reports. See [bulk user imports](../bulk-user-import.md) for upload limits, authorization, and deployment.

## Dedicated email worker

The default campaign connection is `email-database` and lane `email-campaigns`, independent of general `QUEUE_CONNECTION`. Run exactly one consumer matching `EMAIL_CAMPAIGN_CONNECTION` and `EMAIL_CAMPAIGN_QUEUE`:

```bash
php artisan queue:work email-database --queue=email-campaigns --sleep=1 --tries=1 --timeout=60 --max-time=3600
```

`composer run dev:email` uses that default command. If selecting `email-redis`, change the consumer to that connection too; Horizon's existing configuration does not automatically consume it. The dedicated queue reservation is 120 seconds. Web and worker must share the configured cache for heartbeat/locking and use the same configuration/release. Do not run this against real queued recipients during a documentation or diagnostic review.

The [Supervisor email example](../../deploy/supervisor/laravel-crm-email.conf.example) needs local path/PHP/user review. The email and AMI examples use different service users; choose a consistent approved runtime account and verify shared storage permissions. Read [email safety and recovery](../EMAIL_CAMPAIGNS.md) before retries: uncertain sends require reconciliation rather than blind queue retry.

## AMI listeners

The persistent in-process listener is `ami:listen`. The alternate webhook-forwarding listener is `ami:events:listen` and accepts `--reconnect-delay` and `--webhook-url`.

Use the [Supervisor AMI example](../../deploy/supervisor/laravel-ami-listener.conf.example) for the persistent listener. Reconnect delays come from `AMI_LISTENER_RECONNECT_DELAY` and `AMI_LISTENER_MAX_RECONNECT_DELAY`. Select the webhook-forwarding command only when that architecture is explicitly configured.

## Scheduler

Production cron example, after reviewing every enabled policy:

```cron
* * * * * cd /var/www/laravel-crm && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Replace the path/PHP binary and run as the runtime service account. Windows Task Scheduler can invoke PHP with the absolute artisan path every minute and the project as working directory; this does not verify Linux background command behavior. Avoid concurrent OS scheduler entries for the same installation.

| Task | Schedule | Effect |
| --- | --- | --- |
| `activitylog:clean --days=90` | 01:00 daily | Removes old database activity records |
| Notification read-state prune | 01:30 daily | Removes old read receipts (default 90 days) |
| `queue:prune-failed --hours=168` | 02:00 daily | Prunes failed-job history |
| `data-retention:run` | Every minute | Permanently deletes due configured form records |
| `cache:prune-stale-tags` | Hourly | Cache maintenance command |
| Dashboard cache invalidation | Hourly | Forgets named dashboard keys |
| `ReconcileCallStateJob` | Every 15 minutes | Reconciles stale call state |
| `ProcessTelephonyDeadLettersJob` | Every 10 minutes | Surfaces failures as alerts |
| `horizon:snapshot` | Every 5 minutes | Horizon metrics |
| `vicidial:sync-call-history --recent` | Every minute when enabled | Enqueues sync jobs; does not itself prove remote data arrived |
| `email-campaigns:maintain` | Every 5 minutes | Maintenance, reconciliation/cleanup according to email service |

Schedules use application timezone, not the operator's browser timezone. `php artisan schedule:list` lists due tasks without executing them, but installation-local output may reveal identifiers. Do not use `schedule:run` as a harmless health probe.

Related: [monitoring](monitoring.md), [troubleshooting](troubleshooting.md), [maintenance](maintenance.md).

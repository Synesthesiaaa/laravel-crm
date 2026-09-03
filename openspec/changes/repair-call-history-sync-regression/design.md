## Context

Commit `6bca44a` introduced `SyncVicidialCallHistoryJob` and assigned it to the `telephony` queue. The same deployment retained Horizon supervisors for only `default`, `imports`, and `asterisk`, while local startup commands launch a worker without an explicit queue list. The scheduler can therefore dispatch successfully while the synchronization job remains pending and the local store stops receiving new calls.

The Call History page must remain local and asynchronous. The repair is limited to the background path and its diagnostics.

## Goals / Non-Goals

**Goals:**

- Ensure scheduled, manual-refresh, and backfill jobs are consumed by configured workers.
- Keep the job bounded, unique, and isolated from page navigation.
- Recover safely from a checkpoint timestamp that is later than the current sync end.
- Make sync state useful for production diagnosis without exposing credentials or full phone numbers.

**Non-Goals:**

- Reintroducing remote reads into Call History controllers or pagination APIs.
- Replacing Horizon, Redis, or the existing queue topology.
- Changing the VICIdial historical schema/query contract unless tests expose a separate regression.

## Decisions

1. **Add a dedicated Horizon telephony supervisor.** The job already declares the `telephony` queue, so the worker configuration will consume that queue directly. This preserves queue isolation and allows a bounded timeout suitable for the remote database sync. Adding `telephony` to the existing default supervisor was rejected because it would mix the workload with unrelated jobs and hide queue-specific capacity problems.

2. **Make local worker commands consume `telephony` and `default`.** Local development scripts and README examples must match production routing. The queue order keeps Call History work available without starving the existing default queue.

3. **Treat a future checkpoint as invalid recent-sync state.** When `last_call_at` is later than the current sync end, the service logs a sanitized anomaly and uses the configured recent window. It does not mutate the old checkpoint before a successful fetch, so the previous known-good cursor remains recoverable.

4. **Expose diagnostics from persisted sync state.** The existing state row is the source of truth for last attempt, last success, current window, duration, and counters. No remote health call is added to the user request path.

5. **Use Laravel's installed queue middleware namespace.** `WithoutOverlapping` is provided by `Illuminate\Queue\Middleware` in the deployed Laravel version. The job must instantiate that class successfully when a worker invokes its middleware pipeline.

## Risks / Trade-offs

- [A worker is not reloaded after deployment] → Document and test the required Horizon/queue worker restart as a deployment step.
- [The telephony queue has no capacity] → Keep the dedicated supervisor bounded and expose current window/status in health metadata.
- [A future cursor represents a clock or data issue] → Log the anomaly and fall back to a bounded window while preserving local rows and the last successful checkpoint.

## Migration Plan

1. Deploy the configuration, worker entry-point, service, and test changes.
2. Run migrations already required by the local Call History change, then reload configuration and restart Horizon/queue workers.
3. Run `php artisan vicidial:sync-call-history --campaign=<code>` or use the Call History Refresh action and verify the sync state transitions to `healthy`.
4. Rollback removes the new worker configuration and service behavior; it does not delete local Call History records.

## Open Questions

- Production queue-worker process definitions are outside this repository; deployment must apply the checked-in Horizon queue topology or an equivalent `queue:work --queue=telephony,default` configuration.

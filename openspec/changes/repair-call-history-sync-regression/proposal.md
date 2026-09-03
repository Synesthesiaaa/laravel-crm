## Why

The local Call History architecture is correct, but the deployment that introduced it dispatches synchronization jobs to a `telephony` queue that the checked-in Horizon and developer worker configurations do not consume. This leaves sync states stale while the fast local navigation path continues to work. The repair must restore background processing without moving VICIdial work back into HTTP page requests.

## What Changes

- Route the Call History synchronization queue through Horizon and local worker entry points.
- Ensure queue middleware resolves against the installed Laravel queue middleware namespace.
- Make queue and scheduler behavior explicit in the operational configuration and documentation.
- Add recovery for invalid future checkpoints so one corrupted cursor cannot permanently suppress recent calls.
- Expose safe last-attempt, active-window, duration, row-counter, and mapped-scope diagnostics.
- Add regression tests proving queue routing, scheduler dispatch, cursor recovery, and health-state recovery.

## Capabilities

### New Capabilities

- `call-history-sync-reliability`: Keep background Call History synchronization consumable, recoverable, and diagnosable after deployment.

### Modified Capabilities

- `call-history-sync`: Extend synchronization health and cursor semantics to cover deployment queue routing and invalid future checkpoints.

## Impact

- Affected Laravel job, Horizon configuration, local worker scripts, scheduler, sync service, sync-state API payload, tests, and OpenSpec artifacts.
- No new dependencies, tables, or synchronous VICIdial calls are introduced.
- Production operators must reload/restart Horizon or queue workers after deployment so the corrected queue configuration is active.

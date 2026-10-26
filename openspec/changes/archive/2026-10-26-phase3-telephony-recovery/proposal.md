## Why

The audit confirms that transport failures can replay remote writes, uncertain dial outcomes are treated as definite failures, and locally committed telephony changes can lose their remote synchronization intent. It also confirms reconciliation may acknowledge failed transitions, live durations can be negative, and duplicate sessions can select stale Supervisor state.

## What Changes

- Classify VICIdial operations by retry safety and preserve unknown outcomes for non-idempotent writes.
- Keep ambiguous outbound dial reservations active, expose the unknown outcome to the agent, and resolve it only from authoritative callback evidence.
- Persist disposition and lead-field synchronization intent with the related local changes, then process it through a durable worker with explicit failure and no-blind-replay states.
- Route reconciliation through one service and acknowledge AMI events only after a successful state transition; retain retry metadata when processing fails.
- Calculate non-negative elapsed durations and select the newest eligible session per agent.

## Capabilities

### New Capabilities

- `durable-vicidial-sync`: Persist and process remote synchronization intent with explicit retry and unknown-outcome semantics.

### Modified Capabilities

- `call-session-correctness`: Preserve uncertain dial outcomes and reconcile stale calls using authoritative results.
- `telephony-operations-and-reporting`: Return non-negative current-call durations and the newest eligible Supervisor call/session.

## Impact

Affected code includes VICIdial transport and call orchestration services, call/session reconciliation, disposition and Agent Capture write paths, queue jobs, migrations, Supervisor and call-status responses, and focused feature/integration tests. The change adds an outbox table and worker/dispatcher behavior but does not invoke real telephony during validation or replay historical production changes.

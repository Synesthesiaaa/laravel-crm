## Context

Retention policies currently store inclusive date ranges and destruction mode, but execution is limited to a daily command that processes every active policy. The Super Admin page has no immediate run action, scheduled one-time mode, recurring schedule controls, policy-only delete action, or policy-level failure status.

This change crosses the policy schema, model, request validation, schedule calculation, cleanup service, console scheduler, admin routes/controller, Blade interface, and tests. Existing From/To filtering and type-safe selected-field clearing must remain unchanged.

## Goals / Non-Goals

**Goals:**

- Support immediate and scheduled one-time execution.
- Support daily, weekly, and monthly recurring execution.
- Process only active policies whose next execution is due.
- Record next run, last run, affected count, status, and error details.
- Replace deactivation with a policy-only Delete action.
- Backfill existing policies to daily recurring execution at 03:00.

**Non-Goals:**

- New destruction modes or changes to the inclusive From/To record range.
- Queue workers or external scheduling dependencies.
- Deleting form records when a policy configuration is deleted.

## Decisions

### Store execution state on the policy

Add run mode, schedule, next-run, status, and error columns directly to `data_retention_policies`. This keeps the one-policy-per-form configuration model and lets the due query run without a separate execution table. A separate run-history table was considered but rejected because the requested workflow needs current state first and does not require an audit history.

### Use a database-driven due-policy poller

Run `data-retention:run` every minute with overlap protection and select only active policies with a due `next_run_at`. This is preferred over registering a dynamic scheduler event for every policy because the existing command remains the operational entry point and schedule changes are data-driven.

### Separate manual and scheduled execution

Expose `runPolicy(policy, manual)` and `runDue()` in the retention service. Manual recurring runs do not change the existing next-run timestamp; scheduled recurring attempts advance to the next occurrence. One-time success deactivates the policy, while failed or skipped one-time execution clears its next run and requires administrator action.

### Calculate recurring occurrences with Carbon

Daily, weekly, and monthly calculations are centralized in `DataRetentionScheduleService`. Monthly dates beyond the target month's length are clamped to the last valid day. This avoids duplicating calendar logic between the controller, service, and tests.

### Keep policy deletion separate from data destruction

The Delete route uses HTTP DELETE and removes only the policy row. Run Now uses a separate POST action with an explicit confirmation prompt that warns about possible permanent deletion or field clearing.

### Preserve legacy policy behavior during migration

Existing policies are assigned recurring daily mode, a 03:00 run time, and the next daily 03:00 occurrence. Migration must not execute a policy or mark it due immediately.

## Risks / Trade-offs

- **More frequent command invocation** -> Query only active due policies and retain overlap protection/background execution.
- **Repeated destructive retry after a one-time failure** -> Clear `next_run_at` on failed or skipped one-time attempts and show the error to the administrator.
- **Invalid recurring schedule** -> Validate relevant fields conditionally by run mode and recurrence before persistence.
- **Legacy policies changing execution timing** -> Backfill the next daily 03:00 occurrence explicitly during migration.
- **Policy Delete being confused with data deletion** -> Use separate route/action labels and confirmation text stating that only configuration is removed.

## Migration Plan

1. Add the execution columns with recurring defaults and nullable schedule/status fields.
2. Backfill existing policies with daily 03:00 settings and a next daily occurrence.
3. Deploy the model, validation, service, routes, command, and UI changes.
4. Verify migration status, due command registration, and policy-specific Run Now behavior.
5. If rollback is required, deploy the prior application code and reverse the migration; policy configuration columns are removed but existing form data remains untouched.

## Open Questions

None. Immediate versus scheduled one-time execution, recurring modes, and policy-only deletion were approved before implementation.

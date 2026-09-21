# Retention Policy Execution Design

## Context

Retention policies currently store date ranges and destruction mode, but they have no execution lifecycle beyond a daily command that processes every active policy. The admin page can edit a policy through form selection and deactivate it, but it cannot delete policy configuration, run a policy immediately, schedule a one-time run, or configure recurring execution. The command also provides no policy-level status or next-run visibility.

This change extends the existing retention workflow without changing the selected-field clearing rules, date-range semantics, or Super Admin authorization boundary.

## Goals

- Provide immediate and scheduled one-time execution.
- Provide recurring daily, weekly, and monthly execution.
- Process only policies whose calculated next execution is due.
- Show policy execution state, next run, last run, affected count, and errors.
- Replace policy deactivation with policy-configuration deletion while keeping data destruction explicit.
- Preserve existing policies by migrating them to daily recurring execution at 03:00.

## Non-goals

- No new data destruction modes.
- No change to the inclusive From/To record selection behavior.
- No queue or external scheduler dependency.
- No deletion of form records when the administrator deletes a policy configuration.

## Data model

Add the following policy fields:

- `run_mode`: `once` or `recurring`, defaulting to `recurring`.
- `run_at`: nullable datetime used by scheduled one-time policies.
- `recurrence`: nullable `daily`, `weekly`, or `monthly` value used by recurring policies.
- `run_time`: nullable time-of-day value used by recurring policies.
- `run_day_of_week`: nullable ISO weekday number used by weekly policies.
- `run_day_of_month`: nullable day number used by monthly policies.
- `next_run_at`: nullable calculated datetime used by the due-policy query.
- `last_run_status`: nullable `success`, `failed`, or `skipped` value.
- `last_error`: nullable text containing the latest failure or skip message.

Existing `last_run_at` and `last_deleted_count` fields remain. The model will cast `run_at`, `next_run_at`, and `last_run_at` as datetimes and retain the existing date and selected-field casts.

The migration will backfill existing policies as recurring daily policies with a 03:00 run time. Their next execution will be the next daily 03:00 occurrence, preserving the current scheduler behavior without running a policy during migration.

## Execution flow

The retention service will expose policy-specific execution and due-policy execution while preserving the command summary shape.

### Immediate Run Now

The Super Admin Run Now action invokes the selected policy directly. For a recurring policy, this executes one immediate run and leaves the recurring schedule unchanged. For a one-time policy, a successful immediate run deactivates the policy and clears its next execution.

### Scheduled one-time execution

A one-time policy requires `run_at`. When it becomes due, the service executes it once. A successful run records metadata and deactivates the policy. A failed or skipped run records the status and error, remains visible, and clears `next_run_at` so it does not retry destructively every minute; the administrator can reschedule or run it manually.

### Recurring execution

Recurring policies require a recurrence, run time, and the relevant weekday or month day. After each attempt, the service calculates the next occurrence:

- Daily: the next calendar day at `run_time`.
- Weekly: the next selected ISO weekday at `run_time`.
- Monthly: the selected day in the next month at `run_time`, clamped to the last valid day when a month is shorter.

Recurring failures and skips preserve the active policy, record the error, and advance to the next scheduled occurrence so a broken policy does not run repeatedly in a tight loop.

## Scheduler and command

The `data-retention:run` command will be scheduled every minute with overlap protection. It will select active policies whose `next_run_at` is due, initialize a missing next run for legacy-compatible rows, execute each due policy, and report processed, skipped, and affected-record totals.

The command will no longer execute every active policy on every invocation. The existing command remains available for operational use and the existing daily retention schedule is replaced by the due-policy polling schedule.

## Admin interface and routes

The retention page will group policy configuration into date range, destruction scope, and execution settings. The execution controls will show:

- Run Once or Recurring mode.
- One-time date/time when Run Once is selected.
- Recurrence, time, and relevant day controls when Recurring is selected.
- Automatic execution enabled/disabled state.
- Next run, last run, status, and error information.

The policy table will provide:

- Edit, which selects the policy's form and loads its current settings.
- Run Now, protected by a confirmation prompt and limited to the selected policy.
- Delete, protected by a confirmation prompt and implemented as a policy-only delete action.

The controller will add explicit `run` and `destroy` actions. The existing store action will validate execution settings conditionally by run mode and maintain the one-policy-per-form upsert behavior.

## Error handling and authorization

All policy mutations and execution actions remain restricted to Super Admins. Invalid schedule combinations are rejected before persistence. A failed or skipped execution will not deactivate a recurring policy and will be visible through `last_run_status` and `last_error`; the command will continue processing other due policies.

Deleting a policy removes only its configuration row. It must not call the retention service or mutate any form storage table.

## Testing and verification

Automated coverage will include:

- Migration defaults and model casts for execution fields.
- Conditional validation for one-time and daily/weekly/monthly recurring settings.
- Next-run calculations, including month-end clamping.
- Due and not-due policy selection.
- Immediate Run Now behavior for one-time and recurring policies.
- Scheduled one-time deactivation after success and failure visibility without repeated retries.
- Recurring advancement after success, failure, and skip.
- Policy-only deletion and Super Admin authorization.
- Existing date-range and selected-field destruction behavior.

Browser verification will cover the redesigned execution controls, conditional fields, Run Now confirmation, Edit loading, Delete confirmation, and next/last-run status display.

## Risks and safeguards

- **A scheduler frequency change could increase command invocations** -> The command queries only due policies and keeps overlap protection.
- **A one-time destructive job could repeat after a failure** -> Failed one-time policies clear `next_run_at` and require administrator action.
- **Deleting a policy could be confused with deleting data** -> The UI confirmation explicitly says that only policy configuration is removed.
- **Legacy policies could run at the wrong time during migration** -> Migration assigns the next daily 03:00 occurrence rather than marking policies immediately due.

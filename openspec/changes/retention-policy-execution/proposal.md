## Why

Retention policies are saved but can only be processed by a fixed daily command, with no policy-specific Run Now action, scheduled one-time execution, recurring schedule configuration, or visible failure state. The current Deactivate action also does not remove stale policy configuration, making it difficult for Super Admins to manage and diagnose retention jobs safely.

## What Changes

- Add one-time and recurring execution settings to retention policies.
- Support immediate Run Now and scheduled one-time execution.
- Support daily, weekly, and monthly recurring schedules with calculated next-run timestamps.
- Change scheduled cleanup to process only active due policies.
- Add policy-level run status, error, next-run, last-run, and affected-count visibility.
- Replace Deactivate with a policy-only Delete action and add explicit Run Now action.
- Preserve existing date-range destruction, selected-field clearing, and Super Admin authorization behavior.
- Backfill existing policies to recurring daily execution at 03:00.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `data-retention`: Add policy execution modes, due scheduling, manual execution, policy-only deletion, and execution status reporting.

## Impact

Affected areas include the data retention policy migration and model, schedule calculation and cleanup services, admin request/controller/routes, console scheduler and command, retention Blade UI, automated tests, browser verification, and the main data-retention OpenSpec capability.

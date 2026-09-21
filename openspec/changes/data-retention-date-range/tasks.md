## 1. Schema and Model

- [x] 1.1 Add a migration that renames `cutoff_date` to `to_date` and adds nullable `from_date`.
- [x] 1.2 Update `DataRetentionPolicy` fillable fields and date casts for `from_date` and `to_date`.
- [x] 1.3 Update model tests for range casts and legacy null lower bounds.

## 2. Cleanup Service

- [x] 2.1 Update whole-record cleanup to apply inclusive From and To predicates.
- [x] 2.2 Update selected-field cleanup to apply the same inclusive range without changing type-safe clearing.
- [x] 2.3 Add service coverage for range boundaries, legacy policies, records outside the range, and selected-field preservation.

## 3. Admin Data Flow

- [x] 3.1 Validate required `from_date` and `to_date` values in `UpsertDataRetentionPolicyRequest`.
- [x] 3.2 Reject reversed ranges and preserve existing form and selected-field validation.
- [x] 3.3 Persist both dates in `DataRetentionController` while retaining one policy per form and mode behavior.
- [x] 3.4 Update admin feature tests for date-range persistence, validation failures, and legacy policy behavior.

## 4. Admin Interface

- [x] 4.1 Replace the cutoff input with From and To date inputs and field-specific validation errors.
- [x] 4.2 Display separate From and To values in the policy table, including `Any date` for legacy policies.
- [x] 4.3 Preserve automatic form-specific field filtering, mode switching, warnings, and selected-field controls.

## 5. Verification and Specification Sync

- [x] 5.1 Format changed PHP files with Laravel Pint.
- [x] 5.2 Run focused retention/model/admin/command tests and the complete PHPUnit suite.
- [x] 5.3 Verify migration status, retention routes, and daily scheduler registration.
- [x] 5.4 Verify the From/To admin flow and validation in Playwright.
- [x] 5.5 Sync the completed delta into the main `data-retention` OpenSpec specification.

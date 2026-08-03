## 1. Policy persistence and validation

- [x] 1.1 Add `deletion_mode` and nullable `selected_fields` columns to the retention policy migration with backward-compatible defaults.
- [x] 1.2 Add model fillable attributes, casts, and test coverage for whole-record and selected-field policy persistence.
- [ ] 1.3 Extend the retention form request and controller tests for mode validation, eligible field validation, policy upsert, and clearing selections when switching to whole-record mode.

## 2. Type-safe scheduled cleanup

- [ ] 2.1 Add failing service tests proving selected-field cleanup preserves records and unselected values while clearing nullable, text, numeric, and boolean columns type-safely.
- [ ] 2.2 Add failing service tests for unsupported/stale selected fields being skipped without affecting other policies.
- [ ] 2.3 Implement the selected-field cleanup branch with schema metadata inspection, safe identifier validation, and complete update-map validation before mutation.
- [ ] 2.4 Run focused retention service, command, and model tests and preserve the existing whole-record behavior.

## 3. Super Admin retention interface

- [ ] 3.1 Load selected-form field metadata and saved policy values in the configuration controller.
- [ ] 3.2 Add mode controls, dynamic field checkboxes, mode-specific warnings, and mode/field summaries to the Data Retention Blade view.
- [ ] 3.3 Add feature tests for mode rendering, form-specific field filtering, selected-field persistence, and validation errors.

## 4. Full verification and specification alignment

- [ ] 4.1 Run Pint on all modified PHP files and fix formatting issues.
- [ ] 4.2 Run the focused PHPUnit suite, command/schedule checks, and the full PHPUnit suite.
- [ ] 4.3 Verify the Data Retention flow in the browser, including form switching, mode switching, field selection, and saved policy display.
- [ ] 4.4 Sync the modified `data-retention` capability spec, verify the OpenSpec change, and archive it after implementation is complete.

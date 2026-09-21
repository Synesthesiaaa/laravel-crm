## 1. Historical scope aggregation

- [x] 1.1 Reuse the configured normalized system-disposition classification when parsing status breakdown totals.
- [x] 1.2 Apply the selected scope to status totals and per-campaign top-status values while preserving raw call totals and hourly volume.

## 2. Reports interaction

- [x] 2.1 Refresh the historical dashboard when the disposition scope selector changes and keep the control correctly labeled.

## 3. Regression coverage

- [x] 3.1 Add PHPUnit coverage for all, hide-system, and system-only disposition scopes across disposition and status outputs.
- [x] 3.2 Run focused telephony tests and browser validation for scope selection and request refresh behavior.

## 4. Specification and final validation

- [x] 4.1 Run Pint, OpenSpec validation, and repository diff checks.
- [x] 4.2 Synchronize the main telephony reporting specification, review the final diff, and archive the completed change.

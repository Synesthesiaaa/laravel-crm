## 1. Regression coverage

- [x] 1.1 Add service and request tests proving marked numeric fields are valid marker-only custom rules and unmarked numeric fields are rejected.
- [x] 1.2 Add stats tests proving marker-only rules count numeric rows once, sum the marked value, and work for selected-range KPIs and daily reports.

## 2. Rule metadata and validation

- [x] 2.1 Include `is_sale_amount` in editor and resolver metadata while preserving physical-column and campaign safety checks.
- [x] 2.2 Allow empty tag conditions only when the selected amount field is a registered numeric field marked as a sale amount.

## 3. Sales aggregation

- [x] 3.1 Treat a marker-only custom rule as matching rows with a non-blank numeric selected amount value.
- [x] 3.2 Route selected-range KPIs, rolling KPIs, agent leaderboards, and daily reports through the marker-only matcher without changing text/select OR behavior.

## 4. Admin editor

- [x] 4.1 Show forms with marked sale-amount fields as eligible custom-rule forms and initialize marker-only rules when no tag fields exist.
- [x] 4.2 Add accessible guidance and preserve existing tag-rule controls for forms that have text/select fields.

## 5. Verification

- [x] 5.1 Run focused PHPUnit tests, Pint, build checks, and OpenSpec validation.
- [x] 5.2 Verify the custom-rule editor and marker-only flow in responsive browser checks.

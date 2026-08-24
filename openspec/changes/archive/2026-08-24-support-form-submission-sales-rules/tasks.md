## 1. Regression coverage

- [x] 1.1 Add service and request tests for form, tag, marked-amount, and invalid trigger configurations.
- [x] 1.2 Add KPI, leaderboard, selected-range, and daily-report tests for form-submission rules with and without optional amounts.

## 2. Rule metadata and validation

- [x] 2.1 Add trigger constants, persistence normalization, safe inference for old rules, and resolved trigger metadata.
- [x] 2.2 Validate trigger-specific conditions and optional numeric amount fields.

## 3. Sales aggregation

- [x] 3.1 Route all custom aggregation methods through the resolved trigger matcher.
- [x] 3.2 Ensure form triggers count submissions without numeric values and tag triggers retain OR matching and deduplication.

## 4. Admin editor

- [x] 4.1 Expose every active safe form and add a trigger selector with form, tag, and marked-amount modes.
- [x] 4.2 Keep condition and amount controls synchronized with the selected trigger and responsive on mobile.

## 5. Verification

- [x] 5.1 Run PHPUnit, Pint, Vite build, diff checks, and OpenSpec validation.
- [x] 5.2 Verify form and Yes/No rule flows in responsive browser checks.

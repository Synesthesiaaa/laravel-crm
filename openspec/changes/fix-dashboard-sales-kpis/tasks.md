## 1. Regression coverage

- [x] 1.1 Add a failing form-submission test that asserts persisted form rows receive capture timestamps.
- [x] 1.2 Add failing dashboard-service coverage for marked sales without a disposition table and for a fallback Top Agent that only appears in the 24-hour sales window.
- [x] 1.3 Update the dashboard render test to require the Sales and Top Agent cards and reject the Calls (9h) card.

## 2. Dashboard KPI correction

- [x] 2.1 Persist `created_at` and `updated_at` when a form submission row is prepared.
- [x] 2.2 Decouple marked-sale aggregation from telephony storage and apply the sales cutoff to fallback Top Agent ranking.
- [x] 2.3 Remove the Calls card from the dashboard KPI row.

## 3. Validation

- [x] 3.1 Run the focused PHPUnit regression tests and Laravel Pint.
- [ ] 3.2 Verify the authenticated dashboard through the browser, including the Sales amount, 24-hour Top Agent, and absent Calls card.

## 1. Test the rolling sales amount

- [x] 1.1 Update `tests/Unit/Services/DashboardStatsServiceTest.php` so marked-field KPI coverage asserts the summed amount and disposition fallback coverage asserts the lead JSON amount.
- [x] 1.2 Update `tests/Feature/ViewLifecycleRenderTest.php` to assert the dashboard response contains the Sales card's secondary total-value copy.
- [x] 1.3 Run the focused tests and confirm the new assertions fail before implementation.

## 2. Return the rolling sales amount

- [x] 2.1 Change `DashboardStatsService::getKpisForCampaign` to return `sales_amount` and preserve the existing sales count, calls, and top-agent keys.
- [x] 2.2 Aggregate count and summed marked values together for field-driven rows in the configured rolling window, retaining chunking and malformed-value handling.
- [x] 2.3 Sum `sumSaleAmountFromLeadJson` for qualifying fallback disposition rows and round the returned amount to two decimals.
- [x] 2.4 Run the focused service tests and confirm marked-field and fallback totals pass.

## 3. Display both Sales KPI metrics

- [x] 3.1 Add an optional secondary value prop and styling to `resources/views/components/stat-card.blade.php` and `resources/css/app.css` without changing other cards.
- [x] 3.2 Pass the formatted `sales_amount` into the Sales card in `resources/views/dashboard.blade.php`, leaving the count as the primary value.
- [x] 3.3 Run the dashboard view test and frontend build.

## 4. Verify and finalize

- [x] 4.1 Run Pint and all focused tests for the changed service, view, and component behavior.
- [x] 4.2 Verify the dashboard at desktop and mobile widths with Playwright, including the count and total value text.
- [x] 4.3 Validate, sync, and archive the completed OpenSpec change.

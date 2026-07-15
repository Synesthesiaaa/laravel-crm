## 1. Regression coverage

- [x] 1.1 Add failing service assertions proving sales/top-agent use 24 hours while calls use the existing 9-hour window.
- [x] 1.2 Update marked-sale fixtures so a row 10 hours old remains included and a row older than 24 hours is excluded.
- [x] 1.3 Run focused tests and confirm the new window assertions fail before implementation.

## 2. Split KPI windows

- [x] 2.1 Add `dashboard.sales_kpi_window_hours` with default 24 and update the config description.
- [x] 2.2 Use separate call and sales cutoffs in `DashboardStatsService::getKpisForCampaign`.
- [x] 2.3 Include both windows in KPI cache keys and invalidation.
- [x] 2.4 Run service tests and confirm sales amount and Top Agent remain aligned.

## 3. Dashboard labels and verification

- [x] 3.1 Label Sales and Top Agent cards with the sales window while Calls retains the call window.
- [x] 3.2 Run Pint, focused tests, and the frontend build.
- [x] 3.3 Verify the cards in Playwright and confirm the displayed labels and values.
- [x] 3.4 Validate, synchronize, and archive the OpenSpec change.

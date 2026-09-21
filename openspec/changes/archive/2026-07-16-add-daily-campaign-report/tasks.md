## 1. Data aggregation

- [x] 1.1 Add a campaign-scoped daily/MTD report method to `DashboardStatsService` with allow-listed form/table and numeric amount resolution.
- [x] 1.2 Add cache invalidation coverage for the report key and return a stable structure for empty campaigns.

## 2. Dashboard UI

- [x] 2.1 Pass the report data from `DashboardController` to the dashboard view.
- [x] 2.2 Add the four responsive themed tables using dynamic form columns and no “MPI Cards” label.

## 3. Verification

- [x] 3.1 Add focused service/controller/view tests for daily, MTD, dynamic forms, and empty data.
- [x] 3.2 Run Pint and the focused PHPUnit tests.
- [x] 3.3 Verify the authenticated dashboard layout and console output with Playwright.

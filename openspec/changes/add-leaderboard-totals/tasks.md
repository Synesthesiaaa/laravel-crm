## 1. Regression Coverage

- [x] 1.1 Extend `tests/Feature/DashboardSalesRangeTest.php` with assertions that the selected-range leaderboard renders a `Total` row twice—once for the visible table and once for the modal—with the combined sales count and amount.
- [x] 1.2 Run the focused dashboard sales test and confirm the new assertions fail because the aggregate rows are not yet rendered.

## 2. Dashboard Presentation

- [x] 2.1 In `resources/views/dashboard.blade.php`, derive the leaderboard total sales count and amount from the rendered `$agentLeaderboard` rows.
- [x] 2.2 Add matching semantic `<tfoot>` total rows to the leaderboard modal and visible leaderboard, using the existing number formatting and preserving the empty state.
- [x] 2.3 Run the focused dashboard sales test and confirm the new assertions pass.

## 3. Verification

- [x] 3.1 Run Laravel Pint on modified PHP files and rerun the focused feature test.
- [ ] 3.2 Verify the dashboard in Playwright with qualifying sales and confirm both leaderboard surfaces show the aggregate count and amount without console errors. Browser verification was attempted but blocked by the in-app Browser sandbox error and the Playwright MCP browser lock.
- [x] 3.3 Sync the modified `field-sale-attribution` spec into `openspec/specs/field-sale-attribution/spec.md`.
- [ ] 3.4 Archive the completed OpenSpec change after browser verification is available and all checks pass.

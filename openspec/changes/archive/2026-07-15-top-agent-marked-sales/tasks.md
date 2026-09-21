## 1. Regression coverage

- [x] 1.1 Add a failing service test with two agents and marked form submissions asserting Top Agent, qualifying sale count, and summed sale value.
- [x] 1.2 Add a dashboard view assertion for the Top Agent sales summary copy.
- [x] 1.3 Run the focused tests and confirm they fail because Top Agent still uses dispositions.

## 2. Rolling marked-sale Top Agent

- [x] 2.1 Add a chunked helper that groups qualifying marked submissions by agent using `created_at` and the configured rolling window.
- [x] 2.2 Use the field-driven grouping for `top_agent`, `top_agent_sales`, and `top_agent_sales_amount` when marked fields are configured.
- [x] 2.3 Preserve the existing disposition-based Top Agent fallback when marked fields are not configured.
- [x] 2.4 Run the service tests and confirm the regression passes.

## 3. Dashboard and final verification

- [x] 3.1 Render the selected agent's sale count and total value in the Top Agent stat-card secondary line.
- [x] 3.2 Run Pint, focused tests, and the frontend build.
- [x] 3.3 Verify desktop and mobile dashboard rendering with Playwright.
- [x] 3.4 Validate, synchronize, and archive the OpenSpec change.

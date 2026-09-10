## 1. Regression coverage

- [x] 1.1 Extend the dashboard summary feature test to assert the comparable volume/amount column order and the current/previous total values.
- [x] 1.2 Run the focused feature test once to confirm the new assertions fail against the current table markup.

## 2. Dashboard summary table

- [x] 2.1 Reorder the daily summary headers and cells into current/previous pairs for volume and amount.
- [x] 2.2 Add a semantic total row sourced from the existing current and previous summary totals, preserving amount visibility and unavailable-day behavior.

## 3. Verification and closeout

- [x] 3.1 Run focused Laravel tests, formatting, and the frontend build if required by the changed view.
- [ ] 3.2 Perform browser-level verification of the expandable table at representative desktop and mobile widths, then synchronize and archive the OpenSpec change. (Blocked locally at the existing-session confirmation; the browser check did not invalidate the active session.)

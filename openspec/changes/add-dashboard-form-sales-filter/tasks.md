## 1. Regression coverage

- [x] 1.1 Add failing service tests for the default 6:00 AM–6:00 PM range, custom range boundaries, per-form amounts, and the absence of a disposition fallback.
- [x] 1.2 Add failing dashboard-controller and view tests for validated date/time query parameters and the Sales hover modal/filter controls.

## 2. Form-only sales aggregation

- [x] 2.1 Extend dashboard aggregation to return selected-range qualifying sales, Top Agent data, and a per-form marked-field breakdown.
- [x] 2.2 Remove disposition-record data from the dashboard KPI computation and return zero/empty sales data when no valid marked sale field exists.
- [x] 2.3 Resolve the selected range in the dashboard controller, defaulting to the current date from 06:00 through 18:00.

## 3. Dashboard experience

- [x] 3.1 Render the selected range on the Sales card and add the hover, click, and focus-accessible per-form Sales modal.
- [x] 3.2 Submit date and time filters through the dashboard GET route so the card, Top Agent, and breakdown refresh together.
- [x] 3.3 Close the hover modal after the pointer leaves both its card and modal box, with smooth enter and leave transitions, without a backdrop pointer-target loop.

## 4. Validation

- [x] 4.1 Run focused PHPUnit tests and Laravel Pint.
- [x] 4.2 Verify the default and filtered hover-modal flow in the browser at desktop and mobile viewports.

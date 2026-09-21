# Leaderboard Totals Design

## Goal

Show the combined qualifying sales count and sale amount for the selected dashboard range on both agent leaderboard tables.

## Scope

- Add a semantic total footer to the leaderboard in the Top Agent modal.
- Add the same total footer to the visible Agent leaderboard section.
- Keep the existing agent ranking, row values, range filtering, and empty states unchanged.

## Design

The Blade view will sum `sales_count` and `sales_amount` from the existing `$agentLeaderboard` rows once near the top of the view. Each leaderboard table will render those values in a `<tfoot>` row labeled `Total`, formatting counts as integers and amounts with two decimal places. No service, route, API, schema, or dependency changes are needed.

## Validation

Add a feature regression assertion using selected-range sales data that checks the aggregate values appear twice in the rendered dashboard HTML. Run the focused dashboard test, Pint, and Playwright checks for the visible table and modal.

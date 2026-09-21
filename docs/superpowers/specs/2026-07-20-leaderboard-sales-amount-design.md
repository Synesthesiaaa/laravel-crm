# Leaderboard Sales Amount Ranking Design

## Goal

Rank every agent leaderboard by total sale amount, regardless of the number of sales. When two agents have the same total sale amount, use sales count as the first tie-breaker and agent name ascending as the final tie-breaker.

## Scope

- Update the selected-range sales leaderboard used by the dashboard page and leaderboard modal.
- Update the legacy month-to-date `getAgentLeaderboard` service path so its ordering follows the same rule.
- Preserve existing aggregation, date ranges, cache keys, result shapes, limits, and displayed sales/count values.
- Update dashboard copy so the stated ranking order matches the behavior.

## Design

Both service comparators will sort each row by:

1. `sales_amount` descending.
2. `sales_count` descending.
3. `agent` ascending.

The legacy leaderboard may continue calculating submissions for its existing result field, but submissions will no longer influence ranking. No database schema or API shape changes are required.

## Validation

Add regression assertions for both leaderboard paths covering:

- An agent with fewer sales but a larger total amount ranking first.
- Equal amounts being resolved by sales count.
- Equal amounts and counts being resolved by agent name.

Run the focused dashboard service/feature tests, Pint for modified PHP, and the relevant browser verification if the local app is available.

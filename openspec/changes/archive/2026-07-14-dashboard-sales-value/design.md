## Context

The dashboard's `getKpisForCampaign` service currently returns calls, sales count, and top-agent data for the configured rolling window. Sale amounts are already aggregated for the month-to-date leaderboard, but the rolling KPI does not expose an amount and the shared stat card has no secondary content area.

## Goals / Non-Goals

**Goals:**

- Return a two-decimal `sales_amount` value for the same rolling window as `sales`.
- Use marked numeric form fields when a campaign has them, counting and summing each qualifying submission once.
- Preserve the existing disposition/lead JSON fallback and include its amount in the KPI.
- Display the count as the primary Sales card value and the amount as a readable secondary line.

**Non-Goals:**

- No new database columns or dependencies.
- No changes to the month-to-date leaderboard calculation.
- No currency conversion or locale-specific currency symbol; use the dashboard's existing numeric two-decimal presentation.

## Decisions

- **Extend the KPI return contract:** Add `sales_amount: float` to `getKpisForCampaign` rather than calculating totals in Blade. This keeps dynamic schema handling and fallback behavior in the service layer.
- **Reuse field-driven aggregation rules:** Replace the count-only rolling helper with a helper returning both count and amount, using `sumMarkedSaleValues` for each row. A submission contributes once when the helper returns a non-null amount, including numeric zero.
- **Preserve fallback amount semantics:** For disposition-based sales, sum `sumSaleAmountFromLeadJson` for each qualifying sale row, matching the existing leaderboard fallback.
- **Add optional stat-card secondary content:** Add a nullable `secondary` prop to `x-stat-card`, render it below the primary value, and add a small shared CSS class. The Sales card passes `Total value: {{ number_format(...) }}`; all other cards remain unchanged.

## Risks / Trade-offs

- **[Additional row processing for rolling sales]** Field-driven KPI rows must be read to sum values, not only counted. → Keep the existing chunked queries and select only `id` plus marked fields.
- **[Existing consumers of KPI arrays]** Tests or views may assume only the prior keys. → Add the key without removing any existing keys and update the service contract annotation/tests.
- **[Ambiguous currency]** The application has no configured currency format. → Display a plain two-decimal total consistent with the leaderboard rather than inventing a symbol.

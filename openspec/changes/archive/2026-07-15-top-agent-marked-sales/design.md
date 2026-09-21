## Context

The dashboard Sales KPI already uses marked numeric form submissions when configured, but `getKpisForCampaign` still derives `top_agent` exclusively from `campaign_disposition_records`. The two KPIs therefore use different data sources and time columns.

## Goals / Non-Goals

**Goals:**

- Use marked form submissions' `created_at` and `agent` fields for rolling Top Agent ranking.
- Rank by qualifying marked-sale count, then total marked-sale value, then agent name.
- Return the selected agent's sale count and amount for display.
- Keep disposition-based ranking as the no-marked-fields fallback.

**Non-Goals:**

- No change to Calls (9h), month-to-date leaderboard, or sale qualification rules.
- No schema or dependency changes.

## Decisions

- Add a chunked field-driven helper grouped by agent and filtered by `created_at >= $since`; this matches the existing rolling marked-sales aggregation and avoids loading all rows at once.
- Add `top_agent_sales` and `top_agent_sales_amount` keys while preserving `top_agent_calls` for existing fallback consumers.
- Render the marked-sales summary through the already-supported stat-card secondary line as `N sales · Total value: X.XX`.

## Risks / Trade-offs

- **[Different top-agent meaning when fields are configured]** The card changes from call activity to qualifying sales. → Keep the existing fallback and make the card secondary text explicitly identify sales.
- **[Dynamic form schemas]** A table may lack `agent` or `created_at`. → Skip that table, as existing dynamic aggregation helpers do.

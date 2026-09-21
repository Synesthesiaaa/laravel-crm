## Context

The dashboard currently calculates calls, sales, and Top Agent from one `dashboard.kpi_window_hours` cutoff. The requested behavior requires Sales and Top Agent to use the last 24 hours while Calls remain on the existing 9-hour cutoff.

## Goals / Non-Goals

**Goals:**

- Introduce `dashboard.sales_kpi_window_hours` with a default of 24.
- Apply the sales cutoff to marked form sales, fallback disposition sales, and marked-sales Top Agent ranking.
- Keep call counts and fallback Top Agent call ranking on `dashboard.kpi_window_hours`.
- Include both window values in the KPI cache key and invalidation.

**Non-Goals:**

- No change to month-to-date leaderboard dates or sale qualification rules.
- No database schema or dependency changes.

## Decisions

- Keep two explicit Carbon cutoffs: `$callSince` for Calls and `$salesSince` for Sales/Top Agent. This avoids silently changing call reporting.
- Use the configured sales window in the service and Blade labels so the UI describes the actual metric.
- Default the new setting to 24 hours and preserve the existing 9-hour setting for calls.

## Risks / Trade-offs

- **[Cache mismatch]** Existing cached 9-hour KPI entries could be reused. → Include both call and sales window values in the cache key and invalidate the new key.
- **[Existing tests with 9-hour fixtures]** Rows previously outside 9 hours may become valid sales. → Move out-of-window fixtures beyond 24 hours and assert the separate windows explicitly.

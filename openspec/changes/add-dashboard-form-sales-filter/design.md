## Context

The dashboard currently uses a fixed rolling window and retains a disposition-based fallback when a campaign has no valid marked sale field. Users need a business-day range they can adjust, card totals sourced only from configured form fields, and a per-form explanation of the displayed amount.

## Goals / Non-Goals

**Goals:**

- Default the Sales and Top Agent cards to the current date from 6:00 AM inclusive through 6:00 PM exclusive.
- Let users choose a date, start time, and end time with server-side query parameters.
- Aggregate only numeric fields enabled as sale amounts in Field Logic and show a per-form sales count and total in a hover-accessible modal.
- Keep the card, Top Agent, and modal on one filtered source of truth.

**Non-Goals:**

- Do not use campaign disposition records or lead JSON amounts for these dashboard sales metrics.
- Do not add an AJAX endpoint, persist individual users' filter preferences, or alter form-field configuration.
- Do not change the month-to-date leaderboard in this change.

## Decisions

- The dashboard controller will resolve `sales_date`, `sales_start`, and `sales_end` from the GET request. Missing values use the current date, `06:00`, and `18:00`; malformed or non-positive ranges fall back to those defaults. Server-side filtering keeps each visible amount identical after refresh and needs no API.
- `DashboardStatsService` will receive a concrete start/end range and calculate Sales, Top Agent, and a form-keyed breakdown from marked fields only. Disposition queries are removed from this dashboard KPI path. A campaign with no valid marked sale fields returns zero totals and an empty breakdown.
- Form metadata will travel with resolved marked fields so the aggregation can produce one stable breakdown row per configured form. The range uses `created_at >= start` and `created_at < end`, avoiding overlap at adjacent time boundaries.
- The Sales card wrapper opens the existing shared modal on pointer hover, click, and keyboard focus. The modal contains the range form, overall total, and per-form table; submitting the form reloads the dashboard with the selected GET parameters. The card and modal box share a short hover-leave delay, so the modal remains available while the user moves to its controls and closes smoothly after the pointer leaves both.

## Risks / Trade-offs

- [Modal opens frequently while users move across the card] → It opens only when the Sales card receives pointer, click, or keyboard focus, and closes after the pointer leaves both the card and modal box.
- [Existing rows without capture timestamps are excluded] → Submission persistence now creates timestamps; no unreliable historical timestamp is inferred.
- [Large selected ranges scan dynamic form tables] → The default range is twelve hours and existing per-table chunking is retained.
- [No marked Field Logic amount exists] → Present a zero total and empty form table instead of falling back to dispositions.

## Migration Plan

1. Deploy the service, controller, view, and tests together.
2. If view/config caches are enabled, clear them during deployment.
3. Verify the default 6:00 AM–6:00 PM totals and submit a custom date/time filter.
4. Roll back the application files if necessary; no database migration is needed.

## Open Questions

None.

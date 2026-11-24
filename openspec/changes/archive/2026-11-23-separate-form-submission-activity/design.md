## Context

The campaign dashboard displays three Submission Trends charts. Each chart currently combines the campaign total with a series for every form. The existing statistics service already returns aligned bucket labels and counts by form code.

## Goals / Non-Goals

**Goals**

- Use one chart with native form and period selectors and an expandable table below it.
- Keep chart and table in sync and preserve the selection when dashboard data refreshes.
- Preserve campaign scope, the activity visibility setting, and the existing hour and calendar boundaries.

**Non-goals**

- Changes to sales reports, the management dashboard, form storage, or aggregation queries.

## Decisions

- Use the current campaign's form list for the selector and look up counts by form code for each period. Normalize missing counts to zero for available buckets.
- Use native selects and a disclosure in a single chart container. The controls wrap at narrow widths and the table uses the existing dashboard table style.
- Reuse the dashboard's chart registry and loader. Read the counts directly in the table, including when the chart is empty or the chart library fails to load.

## Risks / Trade-offs

- Dashboard refreshes replace the section markup. Carry valid selections and the table disclosure state across refreshes for the same campaign.
- Rapid changes can overlap with chart loading. Serialize chart rendering and skip superseded selections.

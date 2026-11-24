## Context

The campaign dashboard currently shows one chart after the user selects a form and period. Its statistics service already returns counts grouped by campaign form and time bucket. The dashboard editor stores visibility, order, and display preferences in per-campaign JSON.

## Goals / Non-Goals

**Goals**

- Show all three periods for each active form together by default and retain an optional one-form layout.
- Browse weekly and monthly history independently in both layouts, with application-timezone calendar boundaries.
- Let Super Admins choose the layout for an individual campaign.

**Non-Goals**

- Changes to reporting metrics, form permissions, or the management dashboard's own chart.

## Decisions

- Render form sections from the campaign's configured forms. Each chart uses one series and has an expandable server-rendered counts table. Stack the charts at narrow widths and load chart instances near the viewport.
- Add independent week and month query parameters. Default to the current periods, count historical weeks Monday through Sunday and months through their last calendar day, and retain the last-24-hours view. Navigation uses the dashboard's existing soft-navigation lifecycle and keeps the selected sales range.
- Store `activity_mode` in the existing layout JSON. Omitted or unknown values resolve to the all-forms default. Preserve the previous value when authorized edits omit the field.

## Risks / Trade-offs

- Historical submissions can change after Data Master edits. Fetch historical ranges against the existing date indexes and retain the current-period cache and invalidation rules.
- Campaigns with many forms can have many charts. Keep the tables available independently of the chart library and defer mounting charts outside the viewport.

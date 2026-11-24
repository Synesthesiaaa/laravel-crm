## Why

The one-form activity selector makes dashboard users switch charts repeatedly to compare forms and time periods. Weekly and monthly submission activity also needs to be available for completed periods.

## What Changes

- Show last-24-hours, weekly, and monthly charts with expandable counts for every active campaign form by default.
- Let users browse previous weeks and months independently.
- Give Super Admins a per-campaign choice between showing all forms and using the existing form and period selectors.

## Capabilities

### New Capabilities

### Modified Capabilities

- `dashboard-form-activity` gains the all-forms layout and historical calendar browsing.
- `admin-dashboard-layout` persists the campaign's activity layout and restricts changes to Super Admins.

## Impact

The change affects the campaign dashboard, its existing trend queries, and the campaign dashboard layout editor. The preference fits the existing dashboard layout JSON.

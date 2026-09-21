## Why

The dashboard currently derives sales from disposition records and hard-coded lead-data keys, so administrators cannot identify which form fields represent sale value. This makes campaign-specific sale reporting difficult to configure and prevents ordinary form submissions with a configured sale amount from being counted as sales.

## What Changes

- Add a field-logic checkbox that marks a numeric form field as a sale amount.
- Persist the sale-amount designation with each form field and expose it in the field-logic list and edit form.
- Count a form submission as one sale when it contains a non-empty numeric value in any marked field.
- Sum all marked numeric values on each qualifying submission for dashboard sale amounts.
- Use the field-driven totals for configured campaigns while retaining the existing disposition-based totals as a fallback for campaigns without marked fields.
- Cover persistence, validation, dashboard aggregation, and rendered admin controls with automated tests.

## Capabilities

### New Capabilities

- `field-sale-attribution`: Configure sale-amount fields and derive dashboard sales counts and amounts from qualifying form submissions.

### Modified Capabilities

<!-- No existing OpenSpec capability requirements are being modified. -->

## Impact

- `form_fields` schema and `FormField` model.
- Admin Field Logic requests, controller, Blade views, and feature tests.
- `DashboardStatsService` sales KPI and agent leaderboard aggregation.
- Dashboard service tests and cache invalidation behavior.
- No new dependencies or public API routes.

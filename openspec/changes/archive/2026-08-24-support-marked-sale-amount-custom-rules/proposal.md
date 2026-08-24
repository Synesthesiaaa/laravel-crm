## Why

Custom dashboard sales rules currently require a text or select field to act as a tag. Campaigns that use the existing Field Logic `is_sale_amount` marker have no eligible custom trigger, even though that marker already defines which numeric values qualify as sales.

## What Changes

- Allow a custom form rule to use a numeric field marked `is_sale_amount` as its sale trigger when no text/select tag condition is configured.
- Count a submission once when that marked amount field contains a numeric value, including zero, and use the selected field as the optional sale amount source.
- Expose marked sale-amount fields in the admin custom-rule editor with clear guidance and validation.
- Preserve existing text/select OR matching and legacy-mode behavior.

## Capabilities

### New Capabilities

### Modified Capabilities

- `campaign-dashboard-sales-rules`: custom sales rules can use existing marked numeric sale-amount fields as a trigger.

## Impact

- Affected `DashboardSalesRuleService`, `DashboardStatsService`, the admin dashboard rule editor, request validation, and automated tests.
- No database migration or new dependency is required; the existing `form_fields.is_sale_amount` metadata is reused.

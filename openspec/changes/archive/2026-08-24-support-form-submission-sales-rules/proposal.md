## Why

Custom dashboard sales rules currently require a tag condition or a numeric sale marker to qualify a submission. Campaigns where the submitted form itself represents a sale, or where a text field such as Yes/No determines attribution, cannot be configured without relying on a numeric field.

## What Changes

- Add an explicit custom-rule trigger for any submission of the selected form.
- Keep tag-condition triggers for text/select values such as Yes or No, with existing OR and normalized matching.
- Keep marked sale-amount triggers for backwards compatibility.
- Make numeric amount fields optional for form and tag triggers; they only contribute sale value and do not determine whether the form is a sale.
- Show every active form in the editor and let administrators choose the trigger type per rule.

## Capabilities

### New Capabilities

### Modified Capabilities

- `campaign-dashboard-sales-rules`: custom form rules support explicit form-submission, tag-condition, and marked-amount triggers.

## Impact

- Affected `DashboardSalesRuleService`, `DashboardStatsService`, dashboard request validation, the admin dashboard rule editor, and tests.
- Existing stored custom rules without a trigger are inferred safely from their current shape.
- No database migration or dependency is required.

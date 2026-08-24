## Context

The campaign dashboard sales editor currently exposes text/select fields as custom tag conditions and numeric fields as optional amount fields. The existing Field Logic `is_sale_amount` marker is only used by legacy attribution, so a campaign whose forms contain only marked numeric sale fields cannot create a valid custom rule.

## Goals / Non-Goals

**Goals:**

- Reuse the existing `is_sale_amount` metadata as a custom-rule trigger.
- Permit a form rule with no tag conditions when its selected amount field is a registered numeric field marked as a sale amount.
- Keep the same custom aggregation path for selected-range KPIs, leaderboards, and daily reports.
- Make the editor explain and expose marker-only rules without breaking existing tag rules.

**Non-Goals:**

- Do not change the `form_fields` schema or the meaning of legacy mode.
- Do not treat arbitrary numeric fields as sale triggers.
- Do not change text/select OR matching semantics.

## Decisions

- **Represent marker-only rules with the existing shape.** A custom form group keeps `amount_field` and stores an empty `conditions` list. Validation accepts that shape only when the selected field is `is_sale_amount = true`; this avoids a migration and keeps stored layouts compatible.
- **Resolve marker metadata with the field.** `DashboardSalesRuleService` will include `is_sale_amount` in editor and validation metadata, and will preserve the selected marked amount field in the resolved form. Stale or unmarked fields remain rejected.
- **Qualify marker-only rows by numeric presence.** The stats service will consider a row a sale when the marked amount field is non-null, non-blank, and numeric, including a numeric zero. The same field value is summed as the sale amount.
- **Keep tag-first editor behavior.** Forms with text/select fields continue to start with OR tag conditions. Forms without tag fields but with marked amounts become selectable and start with an empty-condition marker rule. The UI labels this as a marked sale-amount trigger.

## Risks / Trade-offs

- [A marked numeric field may contain zero-valued non-sales] → This matches existing legacy `is_sale_amount` behavior, which treats any numeric value as a qualifying marked sale.
- [A saved marker field may later be unmarked or removed] → Resolver validation skips stale/unmarked rules and reports an actionable warning; custom mode does not silently fall back to legacy mode.
- [Existing custom layouts may contain empty conditions unexpectedly] → Only marker fields can validate an empty-condition rule, and existing non-empty tag rules are unchanged.

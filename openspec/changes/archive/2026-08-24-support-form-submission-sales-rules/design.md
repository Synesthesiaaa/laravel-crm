## Context

The dashboard layout already stores campaign sales rules as JSON form groups containing a form, optional amount field, and tag conditions. Empty conditions currently mean a marked numeric amount trigger, which prevents a form submission from qualifying by itself and hides forms without tag or amount metadata from the editor.

## Goals / Non-Goals

**Goals:**

- Let each custom form group explicitly choose form submission, tag conditions, or marked amount as its sales trigger.
- Treat numeric amount fields as optional value sources for form and tag triggers.
- Keep existing custom layouts valid by inferring their trigger when the new property is absent.
- Apply the selected trigger consistently to selected-range KPIs, rolling KPIs, leaderboards, and reports.

**Non-Goals:**

- Do not change legacy mode or the meaning of `is_sale_amount` for legacy attribution.
- Do not change tag matching normalization or OR semantics.
- Do not add a migration; the trigger is stored in the existing layout JSON.

## Decisions

- **Use an explicit `trigger` key.** Values are `form`, `tag`, and `marked_amount`. This removes ambiguity when an administrator wants to count every form submission while also summing a marked numeric amount field.
- **Infer old configurations.** A group with non-empty conditions becomes `tag`; an empty group with a marked amount field becomes `marked_amount`; all other empty groups become `form`.
- **Validate trigger-specific fields.** Form triggers require no conditions; tag triggers require at least one valid text/select condition; marked-amount triggers require an empty condition list and a registered numeric field marked as a sale amount. Amount fields remain optional for form and tag triggers but must be registered numeric fields when supplied.
- **Expose the trigger in the editor.** All active safe forms are selectable. The trigger selector controls which condition panel and amount-field options are shown, with clear helper text for each mode.
- **Keep one aggregation matcher.** Resolved rules carry the normalized trigger mode, and all custom aggregation methods call the same matcher so no KPI/report path diverges.

## Risks / Trade-offs

- [Malformed or stale trigger values are stored] → Resolver skips the affected rule with a warning; request validation rejects new invalid values.
- [Existing custom rules omit `trigger`] → Resolver inference preserves their prior tag or marked-amount behavior and treats other empty rules as form submissions.
- [Administrators select a form with no tag fields] → The form trigger remains available, while the tag option is disabled and the UI explains why.

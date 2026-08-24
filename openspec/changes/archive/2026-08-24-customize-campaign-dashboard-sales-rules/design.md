## Context

`dashboard_layouts` already stores one JSON layout per campaign and the admin dashboard can publish section visibility and order for the campaign held in session. User dashboard sales currently come from numeric `form_fields.is_sale_amount` markers over a selected date/time range. The live campaigns have different forms and fields, but there is no dashboard-level rule that can define values such as `Amenable = Yes` as the qualifying sale event.

The change crosses admin configuration, dynamic form schema validation, dashboard aggregation, report consistency, and responsive UI. It must keep dynamic table and column access allow-listed, preserve existing campaign behavior until an administrator opts into custom rules, and avoid a new dependency.

## Goals / Non-Goals

**Goals:**

- Let an Admin or Super Admin select and customize any active campaign dashboard without changing the application's active campaign session.
- Store per-form tag conditions, accepted values, and an optional numeric amount field with the existing campaign dashboard document.
- Count each matching form submission once and use the same qualifying rows for every sales-derived dashboard result.
- Preserve current marked sale-amount attribution until a campaign explicitly enters custom mode.
- Keep invalid configuration from reaching dynamic SQL and keep dashboards available if saved references later become stale.
- Provide an accessible, responsive rule builder with inline validation, warnings, and a compact configuration summary.

**Non-Goals:**

- Do not add arbitrary SQL, regular expressions, contains operators, AND groups, or cross-form formulas.
- Do not create a general-purpose dashboard widget builder or allow per-user sales rules.
- Do not add new configuration tables, dependencies, or asynchronous rule evaluation.
- Do not change submission activity charts, which continue to represent all submissions.

## Decisions

### Store rules in the existing campaign dashboard JSON

The `dashboard_layouts.layout` document will retain `sections` and add a `sales` object. Custom configuration uses an explicit mode so absence of the object remains distinguishable from an intentional configuration:

```json
{
  "sections": {},
  "sales": {
    "mode": "custom",
    "forms": [
      {
        "form_code": "ezycash",
        "amount_field": "ezycash_amount",
        "conditions": [
          {
            "field_name": "amenable",
            "accepted_values": ["Yes", "Approved"]
          }
        ]
      }
    ]
  }
}
```

Reusing this document preserves the existing one-row-per-campaign boundary and makes layout plus reporting policy one atomic dashboard configuration. A relational rule hierarchy was considered, but its additional tables and models would not provide meaningful foreign-key safety because form data columns remain dynamic strings.

### Keep campaign selection local to dashboard administration

The admin GET route will accept a validated `campaign` query value for editing, and the save request will carry the selected `campaign_code`. These values select configuration context without calling the session-mutating campaign resolver. The user dashboard continues to use its active session campaign.

This prevents administrators from accidentally changing their operational campaign while configuring another dashboard.

### Validate semantic references before persistence

The form request will allow only known nested keys and enforce bounded form, condition, and accepted-value collections. After structural validation, it will verify that:

- the campaign is active and manageable by the current user;
- every form belongs to that campaign and is registered;
- each tag field belongs to its form and uses a supported text or select type;
- each optional amount field belongs to its form and is numeric;
- field and table columns exist before they can be resolved for aggregation.

Runtime queries will resolve table and column identifiers exclusively through campaign repository metadata and schema checks. Submitted identifiers will never be interpolated directly into a query.

### Use form groups with OR conditions and one amount source

Each form configuration has multiple tag conditions and one optional amount field. Accepted values are trimmed and compared case-insensitively. A row qualifies when any condition matches any accepted value. The row ID is the de-duplication boundary, so it contributes one sale and at most one amount even when several conditions match.

One amount field per form avoids ambiguous totals when a row matches more than one condition. More complex Boolean groups and formulas were excluded to keep the rule builder predictable and auditable.

### Keep one sales aggregate as the source of truth

The sales-rule resolver will return normalized, safe rule groups. `DashboardStatsService` will query each configured form once per requested range, evaluate its conditions, and build totals by form and agent. Sales, Top Agent, leaderboard, sales breakdown, and sales-derived daily/month-to-date report values will consume those matching rows. Activity trends remain submission-based.

The default twelve-hour range and exclusive end boundary remain unchanged. Query selection is bounded to the chosen range and rows are processed in chunks.

### Preserve legacy mode explicitly

Campaign documents without `sales.mode = custom` use the existing marked sale-amount behavior. Saving custom rules opts only that campaign into custom mode. The admin UI provides an explicit reset action that removes the custom sales object and returns the campaign to Field Logic attribution. Custom mode requires at least one complete form rule, avoiding an accidental empty configuration that silently reactivates legacy behavior.

### Save atomically and reuse live dashboard refresh

Section layout and custom rules are normalized and written in one `updateOrCreate` operation after the entire request passes authorization and validation. A failed request preserves the existing document and repopulates submitted values with inline errors. A successful save or reset emits the existing campaign-scoped dashboard layout update event so open dashboards refresh.

### Follow the approved responsive configuration layout

The admin page places a campaign selector above stacked section and sales-rule cards, with a compact summary at larger widths and a single-column flow on small screens. Inputs use visible labels, native controls where practical, accessible remove/add buttons, inline errors, focus-visible states, and no hover-only actions. Configuration warnings identify the affected form or field and explain how to recover.

The UI UX Pro Max dataset did not return a verified result for the specific rule-builder query after the allowed retry, so the interface uses the skill's general form, progressive-disclosure, accessibility, and responsive-layout guidance together with existing project components.

## Risks / Trade-offs

- [Dynamic fields are renamed or removed after saving] -> Ignore the stale reference at runtime, show an admin warning, and continue aggregating valid rules.
- [Large custom ranges scan several form tables] -> Apply indexed timestamp bounds first where available, select only required columns, query each form once, and retain chunked processing.
- [Legacy and custom results differ immediately after opt-in] -> Label the active mode clearly and show the configured qualifying values and amount source before saving.
- [Nested rule input is manipulated] -> Restrict allowed keys and collection sizes, validate campaign ownership and field types, and resolve every identifier through allow-listed metadata.
- [A broadcast transport is unavailable] -> Persist successfully, report the broadcast exception using the existing pattern, and allow the next normal refresh to load the saved configuration.

## Migration Plan

1. Deploy backend normalization, validation, matching, and tests before exposing the rule builder.
2. Deploy the updated admin and user dashboard views with the same release.
3. Existing `dashboard_layouts` rows continue in legacy mode because they have no `sales` key; no data rewrite is required.
4. Configure and verify one campaign at a time from the admin selector.
5. Roll back application files if necessary; existing JSON remains readable because unknown `sales` data is ignored by the previous implementation.

## Open Questions

None. The user approved the configuration model, legacy fallback, matching semantics, responsive UI, and validation scope during design review.

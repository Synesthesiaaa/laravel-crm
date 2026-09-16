## Context

The dashboard currently opens with a decorative welcome hero followed by a uniform four-card KPI grid and another uniform monthly metric grid. Equal card size, repeated spacing, and repeated icon treatment flatten the scan order even though sales and current-period performance are more consequential than campaign identity or form count. The application already defines an Operational Signal Desk design system with a dark-first tonal stack, scarce Signal Magenta, semantic status colors, DM Sans, and accessible 44px controls.

## Goals / Non-Goals

**Goals:**

- Make campaign state and the primary current result legible in the first scan.
- Establish a clear primary, secondary, and analytical reading order without changing dashboard data.
- Add character using the existing signal-line motif, tonal surfaces, typography, and brand tokens.
- Preserve responsive DOM order, light/dark themes, modal behavior, chart lifecycle, role/campaign rules, and accessible names.

**Non-Goals:**

- Changing KPI calculations, filters, API contracts, charts, reporting data, or authorization.
- Introducing a new color, font, component library, dependency, or broad redesign outside the dashboard.
- Replacing operational status, telephony, or configurable branding behavior.

## Decisions

1. **Use an operational masthead instead of a generic welcome card.** The existing welcome and campaign content will be retained but composed as a compact identity/context rail. A restrained magenta signal edge and oversized campaign context reuse the established visual language without turning the operating surface into a marketing hero. A larger decorative hero or new illustration was rejected because it would compete with operational data.

2. **Promote sales as the lead KPI and group supporting context separately.** The sales control will span more grid area at desktop sizes, use tabular display numerals, and expose its current time window as nearby context. Top agent, active forms, and campaign remain present but visually subordinate. Keeping four identical cards was rejected because it implies equal importance.

3. **Give monthly performance its own analytical band.** Its heading and current-period summary will be grouped into a distinct surface, while comparison metrics and the chart remain in their existing accessible order. This reduces card-on-card repetition and separates "now" from retrospective analysis.

4. **Scope styling to dashboard semantic classes.** New classes will build only from existing CSS custom properties, spacing, radii, and shadows. The shared stat-card component gains an optional emphasis hook rather than a dashboard-specific duplicate.

5. **Keep responsive and assistive order identical.** CSS grid placement will create desktop emphasis without reordering DOM content. Mobile collapses to a linear reading path: context, lead KPI, supporting KPIs, then monthly analysis.

## Risks / Trade-offs

- **[Risk] Long campaign or agent names could overwhelm the emphasized layout.** → Use min-width safeguards, wrapping/overflow rules, and test long values at narrow widths.
- **[Risk] Stronger sales emphasis could imply the other metrics are unavailable.** → Retain all labels, icons, and cards, and use grouping/scale rather than hiding content.
- **[Risk] New styles could drift in light mode.** → Use only semantic variables and validate both theme bindings where browser access permits.
- **[Risk] Existing configurable section order can move the redesigned blocks.** → Keep the existing section wrappers and inline order values unchanged.

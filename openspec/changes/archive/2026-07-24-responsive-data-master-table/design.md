## Context

The shared `x-table.index` component currently renders a content-sized table inside `.table-scroll-wrap`, while `.md-table-wrap th` prevents header wrapping. This works for wide tables but creates horizontal scrolling for Data Master records with many dynamic fields. The Data Master view already owns the dynamic column labels, formatted values, pagination, and row actions, so it can expose the same record data in a second mobile-only representation without changing routes or services.

The design must preserve the complete table on desktop, avoid horizontal overflow on mobile, keep the existing component consumers stable, and avoid introducing a dependency or a JavaScript layout transformation.

## Goals / Non-Goals

**Goals:**

- Render the full Data Master table at the desktop breakpoint and above.
- Render every configured Data Master field as a labeled value in a stacked mobile card below the desktop breakpoint.
- Preserve field formatting, edit/delete actions, empty states, and pagination.
- Keep the existing shared table component behavior unchanged for other pages unless an explicit responsive-card option is added.
- Ensure long labels and values wrap inside the viewport.

**Non-Goals:**

- Redesigning other admin tables or dashboard report tables.
- Changing Data Master routes, authorization, queries, pagination, or database schemas.
- Adding JavaScript, a new frontend dependency, or a user-controlled column picker.

## Decisions

1. **Use parallel server-rendered markup for desktop and mobile.**
   The Data Master Blade view will render the existing semantic table for desktop and a mobile card list for small screens, with CSS media queries controlling visibility. This keeps the desktop table complete and avoids client-side measurement or data duplication in JavaScript. A single adaptive table was rejected because a dynamic table with many columns cannot remain legible on a narrow viewport while also exposing every field.

2. **Keep the responsive variant local to Data Master.**
   The shared `x-table.index` wrapper will remain unchanged unless a small class hook is needed. Other table consumers currently rely on horizontal scrolling for wide reports, so changing the shared wrapper globally would create an unrelated regression. The Data Master view will use a dedicated wrapper/class for its mobile cards and retain `x-table.index` for its desktop table.

3. **Render each mobile field with an explicit label and value.**
   The mobile markup will iterate over the same `$columns` array and use `$headers[$col] ?? $col` plus the existing `formatValue()` call. Values will use normal whitespace and overflow-wrap rules; no content will be truncated or hidden.

4. **Keep actions available in each representation.**
   The current edit link and delete form will be reused in a mobile action row inside each card. The existing confirmation behavior and route parameters remain unchanged.

5. **Verify responsive layout at rendered widths.**
   Feature assertions will cover the mobile markup contract and desktop table markup. Browser verification will inspect the Data Master route at desktop and phone-sized viewports, confirm no horizontal overflow, and exercise the form-type selector to ensure the table/card content updates normally.

## Risks / Trade-offs

- [Duplicated Blade presentation markup] → Keep both representations in the same view and reuse the same column/value expressions so future field additions remain visible in both layouts.
- [Large mobile cards can be vertically long] → Preserve all fields as requested and use compact spacing plus wrapping rather than hiding data or adding horizontal scrolling.
- [Other table behavior could regress if shared styles change] → Scope new responsive rules to the Data Master classes and leave the existing `.table-scroll-wrap` behavior intact for all other consumers.

## Migration Plan

No database or deployment migration is required. Deploy the Blade/CSS changes, rebuild Vite assets, and verify the Data Master route at desktop and mobile widths. Rollback consists of reverting the view and CSS changes.

## Open Questions

None. Desktop remains the complete table view; mobile uses stacked cards.

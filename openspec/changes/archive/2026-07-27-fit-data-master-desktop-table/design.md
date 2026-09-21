## Context

The Data Master page renders a full record table through the shared `x-table.index` component. That component intentionally supports horizontal scrolling for wide tables, while this page now needs a desktop-specific presentation that keeps every Data Master column within the viewport. The page already has a scroll wrapper around the table, so the change can remain local to the Data Master view and stylesheet.

## Goals / Non-Goals

**Goals:**

- Keep the complete Data Master table rendered on desktop.
- Fit the table to its containing width with predictable column allocation.
- Wrap long headers and values at word and character boundaries.
- Bound the table viewport and scroll vertically when records exceed it.
- Keep the header visible while the table body scrolls.
- Prevent horizontal overflow for the scoped desktop table.

**Non-Goals:**

- Do not change the shared table component or other table pages.
- Do not replace the mobile table with cards or remove any columns.
- Do not change data loading, pagination, actions, validation, or persistence.

## Decisions

1. **Use a Data Master-specific wrapper.** Add `data-master-desktop-table` around the existing table component. This keeps the change isolated and allows the existing inner `table-scroll-wrap` to be configured without changing behavior everywhere else.

2. **Use a fixed table layout at the desktop breakpoint.** Set the table to `width: 100%`, `min-width: 0`, and `table-layout: fixed`. This forces all columns to share the available width instead of allowing long values to grow the intrinsic table width. Long content uses `overflow-wrap: anywhere` and `word-break: break-word`.

3. **Make the existing scroll region vertically bounded.** At the existing desktop layout breakpoint (`1024px`), set the table scroll region to `max-height: min(62vh, 42rem)`, hide horizontal overflow, and enable vertical overflow. This lets pagination remain below the scroll region while large result sets remain usable.

4. **Keep the header sticky inside the scroll region.** Use a sticky `thead` with the existing surface background and a raised stacking level, so wrapped header labels remain visible during vertical review.

5. **Reserve space for actions.** Give the final actions column a stable width and allow its buttons to wrap, preventing action controls from forcing the data columns wider.

6. **Test the server-rendered hook and browser-computed behavior separately.** PHPUnit confirms the Data Master view has the scoped hook; the browser check confirms the compiled CSS produces fixed layout, wrapping, overflow, sticky-header, and no-horizontal-overflow behavior.

## Risks / Trade-offs

- [Very narrow columns on wide-record tables] → Wrapping can create taller rows, but it preserves all columns and avoids horizontal scrolling as requested.
- [Sticky header compatibility with table borders] → Use separate border collapsing with zero spacing and preserve the existing header background/border styling.
- [Existing mobile behavior differs from desktop behavior] → Keep all new rules inside the `1024px` media query and verify a mobile viewport after the build.

## Migration Plan

No data or deployment migration is required. Deploy the Blade/CSS/test changes together, rebuild frontend assets, and roll back by reverting those scoped files if a visual regression is found.

## Open Questions

None; the desktop-only scope and no-horizontal-scroll behavior were approved before implementation.

## Why

The Data Master table is sized to its content and placed in a horizontally scrollable wrapper, which makes records difficult to use on phones and forces users to pan across columns. The page should preserve the complete table for desktop users while presenting the same data in a readable, non-scrolling mobile layout.

## What Changes

- Add a responsive Data Master presentation that keeps the complete tabular view on desktop and tablet-sized screens.
- Add a mobile stacked-card presentation where every configured field remains visible as a labeled value.
- Preserve existing record actions, pagination, empty states, and field formatting in both presentations.
- Prevent horizontal overflow from the Data Master surface on narrow viewports.
- Keep the shared table component's existing desktop behavior for other tables unless they explicitly opt into the mobile-card pattern.

## Capabilities

### New Capabilities

- `responsive-data-master-table`: Responsive Data Master records with a full desktop table and accessible stacked cards on mobile.

### Modified Capabilities

<!-- No existing main capability spec covers the Data Master responsive presentation. -->

## Impact

- `resources/views/admin/data_master.blade.php` will provide the desktop table and mobile card markup.
- `resources/views/components/table/index.blade.php` may expose an opt-in responsive presentation hook while retaining existing consumers.
- `resources/css/app.css` will define the responsive table/card visibility, wrapping, and overflow rules.
- Existing Data Master feature coverage will be extended with rendered markup assertions; no routes, APIs, database schema, or dependencies change.

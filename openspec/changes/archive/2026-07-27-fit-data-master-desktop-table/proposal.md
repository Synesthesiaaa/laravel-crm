## Why

The Data Master table can expand beyond the desktop viewport when records contain long values, forcing horizontal scrolling and making the full record difficult to review. The desktop table needs a bounded vertical viewing area that preserves every column within the available screen width while wrapping long content.

## What Changes

- Scope a responsive layout treatment to the desktop Data Master table.
- Fit the table to its available width with fixed column layout and wrapped headers and cell values.
- Provide vertical scrolling for the table body area while keeping the table header visible.
- Prevent horizontal overflow from the Data Master table without changing the behavior of other tables.
- Add a regression assertion for the Data Master desktop table layout hook.

## Capabilities

### New Capabilities

- `data-master-desktop-table`: Desktop Data Master table sizing, wrapping, and vertical scrolling behavior.

### Modified Capabilities

None.

## Impact

- `resources/views/admin/data_master.blade.php` gets a Data Master-specific layout hook.
- `resources/css/app.css` gets desktop-only table sizing and scrolling rules.
- `tests/Feature/Admin/ExtractionExportTest.php` gets a regression test for the rendered layout hook.
- No API, database, dependency, or mobile table behavior changes.

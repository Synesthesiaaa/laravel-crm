## 1. Regression Coverage

- [x] 1.1 Add a PHPUnit assertion that the Data Master page renders the `data-master-desktop-table` scoped layout hook.
- [x] 1.2 Run the focused regression test and confirm it fails before the implementation is present.

## 2. Desktop Table Layout

- [x] 2.1 Wrap the existing Data Master table component with the scoped desktop layout hook without changing its data, actions, empty state, or pagination.
- [x] 2.2 Add desktop-only CSS that fixes the table to the available width, wraps long content, hides horizontal overflow, enables bounded vertical scrolling, keeps the header sticky, and preserves usable action controls.
- [x] 2.3 Run the focused regression test and confirm it passes.

## 3. Validation and Spec Completion

- [x] 3.1 Run Pint on modified PHP files and run the complete affected feature test file.
- [x] 3.2 Build frontend assets and run `git diff --check`.
- [x] 3.3 Verify desktop and mobile computed layout behavior in the browser, including no horizontal overflow, vertical scrolling configuration, sticky header, and unchanged mobile rules.
- [x] 3.4 Sync the capability spec to `openspec/specs/data-master-desktop-table/spec.md` and archive the completed OpenSpec change after validation.

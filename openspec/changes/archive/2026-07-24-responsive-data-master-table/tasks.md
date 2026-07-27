## 1. Regression coverage

- [x] 1.1 Add a PHPUnit feature test asserting Data Master renders the complete desktop table and the mobile-card field/action markup.
- [x] 1.2 Run the new focused test and confirm it fails before the responsive markup is implemented.

## 2. Responsive presentation

- [x] 2.1 Keep the existing complete Data Master table for desktop and add a mobile-only stacked-card list using the same configured columns, formatted values, and actions.
- [x] 2.2 Add scoped CSS that shows the full table on desktop, shows all record cards on mobile, wraps long content, and prevents Data Master horizontal overflow without changing other shared tables.
- [x] 2.3 Run the focused Data Master tests and confirm the responsive rendering assertions pass.

## 3. Validation and handoff

- [x] 3.1 Format modified PHP files with Laravel Pint.
- [x] 3.2 Build Vite assets and inspect the rendered Data Master page at desktop and phone-sized viewports in Browser.
- [x] 3.3 Run `git diff --check`, confirm no relevant browser console errors or horizontal overflow, and record the final OpenSpec task status.

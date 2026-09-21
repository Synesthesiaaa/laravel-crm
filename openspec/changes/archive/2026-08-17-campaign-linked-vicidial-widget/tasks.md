## 1. Regression Coverage

- [x] 1.1 Update phone widget coverage so the CRM session campaign wins over the stale independent VICIdial session value.
- [x] 1.2 Add source-contract assertions for the soft-navigation campaign-change event.

## 2. Server-Rendered Campaign Bootstrap

- [x] 2.1 Make the layout body datasets use the CRM campaign first and expose campaign display name.
- [x] 2.2 Make the phone widget bootstrap use the CRM campaign first while preserving fallbacks.

## 3. Widget Session Reset

- [x] 3.1 Add a public VICIdial session reset operation for campaign changes.
- [x] 3.2 Register a phone-widget campaign-change handler that clears stale iframe/store state and requires fresh login.

## 4. Soft-Navigation Synchronization

- [x] 4.1 Synchronize fetched campaign datasets onto the persistent document body.
- [x] 4.2 Dispatch `crm-campaign-changed` only when the CRM campaign actually changes.

## 5. Validation

- [x] 5.1 Run focused PHPUnit coverage, Pint, and the Vite build.
- [x] 5.2 Verify campaign navigation and widget state with Playwright MCP.
- [x] 5.3 Review the final diff and mark the change ready for archive.

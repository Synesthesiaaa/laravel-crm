## 1. Backend Cleanup

- [x] 1.1 Remove the widget-only Vicidial session routes and controller actions for `agent-campaigns` and `select-campaign`.
- [x] 1.2 Delete the widget-only campaign lookup service and remove its config flag / validation wiring.
- [x] 1.3 Keep the existing campaign fallback chain and telephony bootstrap behavior unchanged for login, status, pause, logout, and iframe recovery.

## 2. Frontend Cleanup

- [x] 2.1 Remove the campaign selector markup and loading/error state from the softphone widget partial.
- [x] 2.2 Remove the widget-side campaign loading, canonicalization, and session persistence logic from `phone-widget.js`.
- [x] 2.3 Preserve the resolved campaign value flow used by the widget login/status methods.

## 3. Test Coverage

- [x] 3.1 Update the Vicidial session feature tests to remove coverage for the deleted widget-only endpoints.
- [x] 3.2 Add or adjust regression coverage for the campaign fallback chain and widget login flow without a selector.
- [x] 3.3 Run the affected PHPUnit test slice for the Vicidial session and login flows.

## 4. Verification

- [x] 4.1 Run `vendor/bin/pint --dirty --format agent` on the changed PHP files.
- [x] 4.2 Build or otherwise verify the frontend bundle so the widget removal is reflected in the compiled assets.

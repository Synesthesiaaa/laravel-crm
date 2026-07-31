# Data Master, Vicidial Workspace, and Admin Dashboard Design

## Approved outcome

Implement four coordinated improvements:

1. Data Master form selection uses soft navigation so the persistent Vicidial widget is not torn down.
2. Data Master records can be searched across the selected form's safe database columns.
3. Quick Form and Vicidial offer a persisted desktop split view, with the existing floating behavior retained on small screens.
4. Admin and Super Admin users can publish visibility/order for the existing user dashboard sections per campaign; users receive the published layout automatically.

## Architecture

Data Master navigation will opt into the existing `softNavigate` path through a marked GET form. Search remains server-side in `DataMasterService` and is preserved in pagination URLs.

Split mode will use a new shared `workspace` widget-layout key and a small browser controller that coordinates the two existing Alpine components through a custom window event. No telephony session logic changes.

Dashboard customization will use a new `dashboard_layouts` table, `DashboardLayoutService`, an admin-only save endpoint, and a `DashboardLayoutUpdated` campaign broadcast. The dashboard view will keep its existing server-rendered sections and apply normalized order/visibility around them.

## Data and authorization

- `dashboard_layouts.campaign_code` is unique and stores normalized JSON layout data.
- The active campaign is resolved using the same campaign middleware/service already used by the admin and user dashboards.
- Only `Admin` and `Super Admin` can write layout configuration; all authenticated users with campaign access can read it through the server-rendered dashboard.
- Data Master search column names come only from `Schema::getColumnListing` for a table already approved by campaign configuration.

## Verification

Run the affected PHPUnit tests, JavaScript tests, Pint, and Vite build. Use Playwright to verify Data Master form selection does not replace the phone widget/iframe, search results and pagination, split mode at desktop/mobile widths, and admin publication reflected on a user dashboard.

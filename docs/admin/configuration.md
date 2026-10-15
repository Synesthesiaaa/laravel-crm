# System configuration

**Who:** Super Admin. **Navigation:** **Super Admin → Configuration** (`/admin/configuration`). The tabs are **General**, **Branding**, **Disposition**, **Telephony Features**, **Diagnostics**, and **Data Retention**. Email settings have their [own guide](email-configuration.md).

## Branding

Choose **Branding**, enter the company name, and optionally upload a logo/favicon. Logo formats are JPG, JPEG, PNG, or WebP. Favicon additionally accepts ICO. SVG is not an accepted upload in these request rules. Default limits in the request are 5,120 KB for logos and 2,048 KB for favicons, but the actual limits are configurable.

Save, check the confirmation, then review the visible branding. A favicon can be cached by a browser; distinguish caching from upload rejection. Follow the displayed validation error if the file is too large or has an unsupported type. Use artwork your organization is entitled to use.

## Disposition codes and report classification

There are two related tools:

- **Administration → Disposition Codes** manages the actual campaign outcome code/label, sort order, and active state.
- **Configuration → Disposition** configures reporting classification, including system and business outcome groups.

Do not confuse a human-readable label with the code sent to the phone system. Agree exact values with the telephony owner. Changing contacted/qualified/successful groups changes report meaning; review sample periods and communicate the change. Reports do not automatically treat every answered call as a sale.

Create/update of Disposition Codes require Admin or Super Admin through their Form Requests. Team Leaders can view the page and currently inherit deletion access from the route group, which uses a plain Request. This uneven boundary is documented behavior, not a suggested privilege policy. Prefer deactivation when preserving selectable history matters; deletion is not documented as rewriting past outcomes.

## Telephony Features

Feature switches control Agent Screen access and tools such as session controls, inbound groups, predictive dialing, transfers, recording, DTMF, callbacks, and lead tools. They affect UI availability and corresponding feature-gated routes. Enable only the capabilities supported by the deployment and operating procedure. Changing a feature is not the same as configuring a server, provisioning credentials, or testing actual calling.

Agent Screen access also gates its configuration destination in the sidebar. If users report a missing control, check both role and feature state before diagnosing JavaScript.

## Diagnostics and dashboard layout

Use **Diagnostics** to inspect configured integration health when authorized by your operating procedure. Diagnostic actions can contact external sources; they are not merely static documentation. Interpret health results with their source/time and do not expose secrets.

**Management Dashboard** also permits Admin and Super Admin users to adjust campaign dashboard section order/visibility and related amount presentation. Team Leaders can view the dashboard but cannot save its layout. Sales attribution rules live with form configuration; use [forms and field logic](forms-and-field-logic.md) and [report definitions](../user-guide/reports.md).

For Data Retention and Activity Log, continue to [retention and audit](retention-and-activity-log.md).

Source: [configuration tabs](../../resources/views/admin/configuration.blade.php), [configuration controller](../../app/Http/Controllers/Admin/ConfigurationController.php), [branding request](../../app/Http/Requests/Admin/UpdateBrandingRequest.php), [feature service](../../app/Services/TelephonyFeatureService.php), [disposition controller](../../app/Http/Controllers/Admin/DispositionCodesController.php), [dashboard controller](../../app/Http/Controllers/Admin/AdminDashboardController.php).

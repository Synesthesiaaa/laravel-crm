# Roles and permissions

The application has four formal account roles: **Agent**, **Team Leader**, **Admin**, and **Super Admin**. These exact strings are defined in the User model and validated by User Access. Job responsibilities such as Quality Analyst (QA) and Operations Manager (OM) are not additional formal roles.

## Practical access map

| Capability | Agent | Team Leader | Admin | Super Admin |
| --- | --- | --- | --- | --- |
| Dashboard, personal attendance, Campaign Forms, Call History | Yes | Yes | Yes | Yes |
| Agent Screen and enabled call tools | Feature-dependent | Feature-dependent | Feature-dependent | Feature-dependent |
| Reports, Supervisor, Staff Attendance, Records List | No | Yes | Yes | Yes |
| Data Master, Capture Records, Extraction, Disposition Records | No | Yes | Yes | Yes |
| View Field Logic and Disposition Codes | No | Yes | Yes | Yes |
| Create/update Field Logic and Disposition Codes | No | No | Yes | Yes |
| Delete Field Logic and Disposition Codes | No | Currently route allows | Yes | Yes |
| Save management dashboard layout | No | No | Yes | Yes |
| User Access, Campaigns, Forms, VICIdial Servers | No | No | No | Yes |
| Lead Hopper, Agent Screen Configuration, Attendance Statuses | No | No | No | Yes |
| Configuration, retention, branding, Activity Log | No | No | No | Yes |
| Email Configuration and Email Campaigns | No | No | No | Yes |

“Yes” describes the checked-in route/request boundary, not a promise of working external services or entitlement to every customer's data. Telephony controls can still be disabled or rejected by the external system. Call History gives non-management users a personal context while management users receive broader campaign context.

## How enforcement works

The custom `CheckRole` middleware compares the authenticated user's `role` column with the explicit allowed strings on each route. User helper methods treat Admin/Super Admin as management and Team Leader-or-higher for `isTeamLeader()`. Some Form Requests impose an additional narrower check.

The application also uses Spatie role/permission facilities, but a seeded permission name does not override these direct route and request checks. Assigning an abstract permission is not sufficient to make a Super Admin-only route available to an Admin. Hiding a sidebar item also is not the access-control mechanism.

The uneven deletion behavior in the table is intentional documentation of the current implementation: Field Logic and Disposition Codes create/update use Admin-only Form Requests, while their delete actions inherit the Team Leader-or-higher route group and use a plain Request. Do not describe Team Leaders as read-only administrators.

## Campaign and public-surface boundaries

Active CRM campaign selection is context resolution. `CampaignService::resolveCampaignForRequest()` selects a configured campaign from an explicit request/session/fallback and updates session labels; it does not itself enforce a user-to-campaign membership relationship. A default campaign on a user is not a documented isolation boundary. Individual endpoints have their own scoping logic, so review each sensitive flow rather than assuming tenant isolation.

**Operations Insights** and its data/records endpoints are currently public routes outside authentication, with guest access covered by checked-in tests. QA/OM teams may use that view, but access is not limited to those job titles. Ordinary Reports remain restricted to Team Leader/Admin/Super Admin. Deployment owners should evaluate exposure independently of the sidebar. See [Supervisor and Operations Insights](admin/supervisor-and-operations.md).

Before granting a role, use the least access needed for the actual duty and review the [administration guides](admin/README.md). This document describes behavior; it does not change authorization.

Source: [User model](../app/Models/User.php), [role middleware](../app/Http/Middleware/CheckRole.php), [web routes](../routes/web.php), [admin routes](../routes/admin.php), [field create authorization](../app/Http/Requests/Admin/StoreFieldLogicRequest.php), [disposition create authorization](../app/Http/Requests/Admin/StoreDispositionCodeRequest.php), [campaign resolution](../app/Services/CampaignService.php).

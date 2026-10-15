# Authorization

Authorization combines route middleware, Form Request authorization, explicit controller policies, feature gates, and service ownership rules. Inspect all layers before changing access. An authenticated route is not automatically a resource policy check.

`CheckRole` compares `users.role` literally against allowed values: `Agent`, `Team Leader`, `Admin`, `Super Admin` are defined by `User`. The current middleware does not delegate to Spatie permission checks, even though permission tables/package are installed.

| Boundary | Current source rule |
|---|---|
| Main app | Session `auth` and `campaign` middleware |
| VICIdial session routes | Session auth; campaign resolver in service/controller; telephony feature gates |
| Supervisor/report realtime | `Team Leader`, `Admin`, `Super Admin` route roles |
| Admin outer group | `Team Leader`, `Admin`, `Super Admin`; individual policies may narrow further |
| Configuration, users, servers, campaigns, form administration, email | Inner `Super Admin` route group |
| Admin dashboard layout update | Additional `Admin,Super Admin` role gate |
| Telephony controls | `telephony_feature:<feature>` middleware in addition to route auth |
| Token disposition API | `auth:sanctum`; required campaign query, not browser campaign middleware |

AppServiceProvider explicitly registers policies for users, campaigns, forms, disposition codes, and VICIdial servers; `EmailCampaignPolicy` also exists. Campaign/Form policies permit admin reads but reserve writes for Super Admin. Disposition policy permits admin CRUD. Server policy reserves all actions for Super Admin. The user policy's `before` grants Super Admin access before method-specific checks, so do not infer a self-delete prohibition from `delete()` alone. Email campaign update requires Super Admin and draft status.

Campaign middleware chooses a configured active campaign; it is not a membership policy. Dashboard broadcast authorization similarly checks that the campaign resolves, not a user-campaign relation. Do not document tenant isolation that the source does not implement.

Private broadcast user/agent channels require matching user ID; supervisor channel checks supervisor roles; activity-log requires Super Admin. Presence allows authenticated users and publishes ID/name/role. See [realtime](realtime.md).

Public route visibility is intentional source fact: `/operations-insights`, its two JSON endpoints, `/api/telephony/health`, `/api/websocket/health`, webhook ingress, welcome page, and signed unsubscribe routes are outside session auth. Network access restrictions are a deployment responsibility, not a currently declared route restriction. Never place real secrets in example URLs, screenshots, logs, or documentation.

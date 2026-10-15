# Supervisor and Operations Insights

## Supervisor dashboard

**Who:** Team Leader, Admin, Super Admin. **Navigation:** **Administration → Supervisor** (`/admin/supervisor`).

1. Choose the **CRM campaign** in the **VICIdial Routing** section.
2. Confirm the selected server and routing message. This campaign determines server routing independently of the agent's VICIdial session campaign.
3. Check freshness and source health before acting on agent counts. A **Live** heading is not sufficient when a stale/unavailable message appears below it.
4. Review online, available, on-call, and paused agents, along with the displayed operational information.
5. Use **Retry** after a transient failure. Escalate persistent server-not-configured, degraded, or unavailable results with campaign/time/error details.

Admin and Super Admin users can see technical source diagnostics when available. Do not copy credentials or unnecessary customer information when escalating an issue.

## Supervisor actions

The application provides monitor, whisper, force-pause, force-logout, and notification endpoints for these management roles. They call the external phone integration and can affect a real agent session. Confirm the exact agent, campaign, and operational reason before using an exposed control. Success depends on credentials and remote permissions; a CRM role does not establish that the remote server permits the action.

Notifications can target USER, USER_GROUP, or CAMPAIGN. Local mirroring occurs after successful remote delivery for matching USER and CAMPAIGN recipients; USER_GROUP is not mirrored to local users by this controller. See [notifications](../user-guide/notifications.md). A message being sent does not prove it was read.

**Staff Attendance** is a separate administration destination for realtime attendance and filtered login/status history. It does not substitute for phone agent status. **Telephony Monitor** also has a management route (`/admin/telephony-monitor`), although it is not currently listed in the shared Administration navigation items.

## Operations Insights

Open `/operations-insights` using the application's base address. This surface has **Overview**, **Call Reports**, and **Data Records** sections, with **Past Performance**, **Live Activity**, and **Today** reporting contexts. Select the campaign and scope, then refresh and review source/availability messages before interpreting figures. Historical requests validate a maximum 31-day range.

Quality Analysts and Operations Managers may use this surface as organizational responsibilities, but **QA and OM are not formal application roles**. Do not create those role names expecting route access to work.

**Access boundary:** the current routes for Operations Insights and its data/records APIs are outside the authenticated route group. The source and guest-access tests explicitly allow guest viewing, including paginated form data. It is not protected merely because the ordinary Reports page is role-restricted. Deployment owners must assess who can reach this URL and its data. These documentation changes do not add a restriction. No write controls in this surface does not mean the information is non-sensitive.

For metric formulas and unavailable-versus-zero behavior, use [reports](../user-guide/reports.md). For configuration, use [configuration](configuration.md).

Source: [supervisor controller](../../app/Http/Controllers/Admin/SupervisorController.php), [supervisor UI](../../resources/views/admin/supervisor.blade.php), [supervisor actions](../../app/Http/Controllers/Api/SupervisorTelephonyController.php), [public route placement](../../routes/web.php), [Operations controller](../../app/Http/Controllers/OperationsInsightsController.php), [guest-access tests](../../tests/Feature/OperationsInsightsTest.php) (inspected, not executed for this documentation).

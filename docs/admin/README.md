# Administration guide

Start with your role: [Team Leader](team-leader.md), [Admin](admin.md), or [Super Admin](super-admin.md).

Management tools appear in two sidebar sections. **Administration** is available to Team Leaders, Admins, and Super Admins; **Super Admin** contains the restricted setup tools. A page being visible does not imply permission for every write action. Read [roles and permissions](../roles-and-permissions.md) before assigning duties.

| Responsibility | Guide |
| --- | --- |
| Create accounts and maintain access | [User access](users.md) |
| Configure campaign context, routing, and imports | [Campaigns and Lead Hopper](campaigns.md) |
| Define forms, fields, visibility, and sales attribution | [Forms and field logic](forms-and-field-logic.md) |
| Correct and export records | [Data management](data-management.md) |
| Monitor agents and interpret Operations Insights | [Supervisor and operations](supervisor-and-operations.md) |
| Set branding, reporting groups, and telephony features | [Configuration](configuration.md) |
| Manage data deletion and review audit events | [Retention and Activity Log](retention-and-activity-log.md) |
| Configure SMTP | [Email configuration](email-configuration.md) |
| Prepare and send recipient campaigns | [Email campaigns](email-campaigns.md) |

For a new deployment, start with [first campaign setup](../getting-started/first-campaign.md). Establish user access, campaign/server mapping, form definitions, and fields before asking agents to enter production records. Use a designated safe environment to validate changes that affect calls, data deletion, or outbound communication.

This guide describes source behavior. It does not assert that runtime credentials, connectivity, background processing, or the database are ready. No live actions are needed to read these instructions. Treat exported files, recordings, customer information, and operational credentials according to your organization's access and retention policies.

Source: [administration routes](../../routes/admin.php), [navigation labels](../../app/Support/AdminNavigation.php), [role checks](../../app/Http/Middleware/CheckRole.php).

# Super Admin working guide

Super Admin controls restricted system setup in addition to the operational management tools. Grant this role only to people responsible for that access. It can expose private credentials/documents and trigger destructive or externally visible actions.

## First setup

1. Follow [installation](../getting-started/installation.md) and [first campaign](../getting-started/first-campaign.md) in an isolated environment.
2. Use **User Access** for the four formal roles and verified phone profiles; see [users](users.md). Replace any development seed password before sharing access.
3. Use **Campaigns**, **VICIdial Servers**, **Forms**, **Field Logic**, and **Agent Screen Configuration** to prepare the intended workflow. CRM campaigns, remote campaigns, forms and captures are different concepts.
4. Review **Configuration** for branding, reporting classifications, feature access and retention. Agent Screen defaults off; enabling a feature does not provision its external service.

## Daily and periodic work

Use **Management Dashboard**, **Supervisor**, **Staff Attendance**, **Reports**, and record tools for routine operations, following the [Admin guide](admin.md). Review **Activity Log** with filters when investigating changes; the bounded recent screen is not a permanent complete audit.

Prepare email only through **Email Configuration** and **Email Campaigns**. Confirm correct recipient/document assignments, dry-run results, gates and dedicated worker readiness before any authorized send. Read [the full email guide](../EMAIL_CAMPAIGNS.md); payroll cannot proceed when a required protection is unavailable. Provider acceptance is not confirmed inbox delivery.

Before retention **Run**, campaign/form/field deletion, or bulk record deletion, confirm the precise scope and recovery procedure. [Retention](retention-and-activity-log.md) is permanent data work, not a preview. Exports, backups and external systems are separate copies.

## Troubleshooting and handover

Review source/worker state with the system operator for missing history or queued campaigns. A successful account update does not guarantee remote credential sync. Keep operational endpoints' public exposure under review; do not assume campaign context is membership isolation. [Security](../operations/security.md) and [operations](../operations/README.md) describe infrastructure responsibilities.

Use synthetic checks for configuration changes, document their results, and provide sanitized errors when escalating. Finish local phone/attendance work and sign out. Related: [configuration](configuration.md), [troubleshooting](../operations/troubleshooting.md), [permission matrix](../roles-and-permissions.md).

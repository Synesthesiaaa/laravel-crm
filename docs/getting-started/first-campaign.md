# Prepare the first campaign

**Who:** Super Admin prepares campaign/form/server configuration. Admins and Team Leaders supervise only the features their routes and requests permit. Staff need the correct operating campaign; a per-user campaign membership assignment interface is not implemented.

Use a synthetic demonstration campaign first. Names below are illustrative, not preloaded business data.

1. Sign in as Super Admin and open **Campaigns**. Create an active campaign with a unique code such as `training`, display name, and order. Follow [campaign administration](../admin/campaigns.md) for exact fields and mapping behavior.
2. Open **Forms**, select the campaign, and create its form with a unique form code and safe storage-table name. Forms use registered database tables; changes are not merely visual labels. Configure fields through **Field Logic** and test required/optional fields with synthetic data. Read [forms and field logic](../admin/forms-and-field-logic.md).
3. Open **User Access** and create accounts with one of the four verified roles. Set phone credentials only for a controlled telephony test. These credentials and a user's default VICIdial campaign do not create a general CRM campaign membership restriction.
4. For calling, configure the campaign-linked **VICIdial Server** and explicit external campaign mappings, then validate the remote account, AMI, WSS/SIP, and one audio owner through [integration guides](../integrations/README.md). A CRM campaign and an external VICIdial campaign are different identifiers.
5. **Agent Screen Configuration** manages capture fields/webforms. In **Configuration**, enable Agent Screen access only after its behavior and intended exposure are reviewed; it defaults off. Other feature flags do not establish that the PBX supports each enabled button.
6. Sign out and sign into the training campaign using a synthetic Agent account. Confirm the displayed campaign, open its form, submit synthetic values, and review the result with an authorized supervisor. Validate dispositions and callbacks only in a controlled PBX environment.
7. Review report source/time windows and data scope. A new empty campaign is not a reporting failure. Do not infer call activity from form submissions or CRM login events.

If a newly saved campaign does not appear, check active state and the campaign cache/configuration invalidation. If a campaign is missing or invalid at login the resolver can select a fallback; verify the page label before writing data. Do not enter real records to test installation.

Expected result: staff can select a configured work area and use its verified forms; separately configured integrations can be validated under their own acceptance procedure. Next: [daily workflow](../user-guide/daily-workflow.md), [troubleshooting](../operations/troubleshooting.md).

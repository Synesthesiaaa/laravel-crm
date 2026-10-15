# Campaigns, routing, and Lead Hopper

**Who:** Super Admin. **Navigation:** **Super Admin → Campaigns**, **VICIdial Servers**, and **Lead Hopper**.

## Create and maintain a CRM campaign

1. Open **Campaigns** and create a campaign with its business name and unique code. New codes accept lowercase letters, numbers, and underscores, up to 50 characters.
2. Add a useful description and display order; keep the name recognizable to agents.
3. Configure predictive settings only when your operating process and telephony setup support them. Validation allows a delay of 1–300 seconds and maximum attempts of 1–20.
4. Save, then configure [forms and fields](forms-and-field-logic.md) and the required server mapping.

The campaign edit action can change code, name, active state, and predictive settings. A code is a technical identifier referenced by related data; do not treat renaming it as a guaranteed migration of every historical record. The delete action deactivates a campaign and refuses when any related forms exist. Deactivating forms does not necessarily remove that relationship.

## Connect the campaign to VICIdial

CRM and VICIdial campaign codes are different concepts. **VICIdial Servers** stores the server/API/database connection configuration. Keep credentials restricted and use the deployment operator's verified values. Do not paste secrets into support tickets or documentation.

On **Campaigns**, choose an active server belonging to that CRM campaign, load its VICIdial campaign catalog, and choose the intended codes. Saving a mapping validates the codes against the chosen server and replaces that CRM campaign's previous mapping set. Check all intended codes before saving. A successful CRM campaign creation alone does not provide calling or reporting connectivity.

The report and supervisor context can use this CRM mapping without changing an agent's separate VICIdial session campaign. Have an operator validate routing and source health in a safe environment before live use. See [configuration](configuration.md) for diagnostic controls and [supervisor](supervisor-and-operations.md) for interpreting routing status.

## Lead Hopper imports

**Lead Hopper** shows status counts, up to 25 recent hopper leads, and up to 15 recent imports for the session campaign. For an import:

1. Verify the active campaign and choose a configured **Form Type**.
2. Prepare CSV headers matching that form's configured field names and required values. A missing date is defaulted by the import job; request IDs are generated rather than accepted from CSV.
3. Select the **CSV File** (CSV/TXT upload, maximum 10,240 KB) and choose **Queue Import**.
4. Review the import result later. Queued means accepted for background processing, not completed. The `imports` queue must be processed by the deployment's worker configuration.

**Important implementation limit:** despite the page title, the current `ImportLeadsCsvJob` calls the campaign form submission service for each row. It creates form submissions and their recorded history; the job does not insert Lead Hopper rows or promise to create VICIdial leads. Do not use this upload as a guarantee that leads became dialable.

An import can accept some rows and fail others. Its failed status can therefore coexist with a nonzero imported count. There is no all-or-nothing rollback across the file or documented CSV deduplication. Re-uploading the whole file or retrying an interrupted job can duplicate accepted form rows. Reconcile results before retrying. If the page says the schema is not ready, send the issue to the deployment operator rather than asking an agent to change the database.

Source: [campaign controller](../../app/Http/Controllers/Admin/CampaignsController.php), [campaign validation](../../app/Http/Requests/Admin/StoreCampaignRequest.php), [Lead Hopper controller](../../app/Http/Controllers/Admin/LeadHopperController.php), [import job](../../app/Jobs/ImportLeadsCsvJob.php), [server controller](../../app/Http/Controllers/Admin/VicidialServersController.php).

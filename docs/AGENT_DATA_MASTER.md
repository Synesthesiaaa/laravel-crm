# Agent Data Master

Super Admin configures access at **Configuration > Data Master**. Select a campaign and choose **Load campaign**, then:

1. Select **Enable agent Data Master**.
2. Leave **Own submissions only** on to show each agent only submissions linked to their account. Turn it off to show all users' submissions in permitted campaign forms.
3. Select the **Allowed forms**. At least one active form is required when enabling access.
4. Choose **Save Data Master permissions**.

These settings apply to all agents using that campaign. Agent access starts disabled. New forms require explicit selection; inactive or deleted forms cannot be viewed. Changes take effect on the next request without a worker restart or cache flush. Agents see a Data Master sidebar link only while access is available.

## Agent workflow

Data Master is available at `/data-master` in the current campaign. Choose a permitted form and **Load form**. Loading a different form resets filters so that filters from another schema are not applied accidentally.

Use search, **Submitted from / Submitted through**, or expand **Column filters** to match text in particular visible columns. Filters combine and remain in pagination links; **Clear filters** retains the selected form. Results are paginated at 20 records. The submission date range is inclusive and uses the storage `created_at` timestamp when available, otherwise the form `date` column.

The agent page provides no edit, delete, import, or export actions. Existing administrative Data Master permissions remain unchanged, and agents cannot use those write routes directly. Client-supplied campaign, table, user, and ownership parameters do not override server permissions.

## Ownership and historical records

Own-only visibility matches the authenticated user ID against `crm_call_history`, including campaign, form and record ID. Display names are not used: users can share names or change them. Older records without a reliable account link remain hidden in own-only mode. Administrators can choose campaign-wide visibility to include legacy rows in exclusively mapped form tables.

If multiple configured forms share a physical storage table, even campaign-wide visibility requires matching campaign/form submission history. Unattributed rows in shared tables remain hidden to prevent cross-campaign disclosure. Inactive and soft-deleted mappings are included when determining whether storage is shared.

Only active metadata fields and approved system columns are selected or searched. Retired physical columns remain stored but are not exposed. Missing form storage produces a safe unavailable page and does not create or change tables.

## Storage and deployment

Permissions are stored as audited JSON in `system_settings`, one `agent_data_master_<campaign-code>` record per campaign. Allowed forms are stored by ID. Missing or malformed settings deny access. There is no new migration or dependency.

Deploy the source and rebuilt frontend assets using the normal application deployment process. Leave access disabled until Super Admin explicitly grants it. This change does not add an agent-to-campaign assignment system or change existing campaign login behavior.

## Validation fixture

Run `php tests/Support/agent-data-master-browser.php seed`, then `php -S 127.0.0.1:8881 -t public tests/Support/agent-data-master-browser.php` for isolated browser validation. The CLI/server-only fixture uses its own synthetic SQLite database and storage under `storage/framework/testing/agent-data-master-browser`; no production users or data are changed. Stop the server with Ctrl+C when finished.

The repeatable browser checks run with `node tests/Support/agent-data-master-browser.mjs <absolute-path-to-installed-playwright/index.mjs>`. They use installed Chrome headlessly (or `PLAYWRIGHT_CHANNEL`), create isolated browser contexts, capture four widths per surface plus expanded mobile filters, and write evidence under `.impeccable/review/agent-data-master`. The fixture's CLI-only `storage-unavailable` and `storage-restore` commands temporarily change only its synthetic form mapping for the recovery check.

Synthetic accounts: `dm-super`, `dm-agent`, `dm-other`; password `Synthetic-DM-2026!`. These accounts exist only in the isolated fixture. Real SIP, Reverb and VICIdial services are not provided by this fixture; their shared-shell connection errors do not establish integration behavior.

# Review, correct, and export data

**Who:** Team Leader, Admin, Super Admin through the Administration route group. These tools can expose and change customer data. Follow your organization's approval and export-handling process.

## Choose the correct record store

| Destination | What it is for |
| --- | --- |
| **Records List** | Submission activity with date/agent filters and a Calls tab. Submission entries are paginated in groups of 25. |
| **Data Master** | The actual campaign form rows, selected by form type. Includes search, edit, and delete. |
| **Capture Records** | Agent Screen captures, with campaign, agent, lead ID, phone, and date filters; edit, delete, and CSV export. Lists 50 per page. |
| **Disposition Records** | Review of lead/disposition history. |
| **Extraction** | CSV extraction of selected campaign form data. |

Do not assume deleting a record in one store deletes every related call, notification, history entry, or external lead.

## Correct a campaign form record

1. Open **Administration → Data Master**.
2. Verify the campaign and select the form type. Search if needed; the controller trims search text to 100 characters.
3. Open the correct record and compare its ID, customer details, and original values.
4. Change only the intended fields and save. The controller skips internal ID/timestamp fields and checks the table against campaign-allowed storage tables.
5. Reopen the record and confirm the result. Corrections can affect dashboard results.

Data Master edits are not identical to submitting the original form: its update controller accepts values from existing record columns and performs percentage normalization, rather than reusing all of the ordinary FormSubmissionRequest validation. Managers should review values carefully. Delete is a data-changing action; confirm the record/store and obtain any required business approval before using it. No recycle bin or restore action is promised here.

## Review captures

Open **Capture Records**, choose the campaign, narrow the filters, and inspect the matching record. Editing allows lead ID, phone number, and configured capture values. The record is looked up within the selected campaign. Export uses the same filter inputs and includes record identity, campaign, creation time, agent, lead/phone information, and configured capture columns. Check the filter scope before downloading.

## Export campaign forms

1. Open **Administration → Extraction**.
2. Select the data type/form, or all supported form tables.
3. Set optional start and end dates; end must not precede start.
4. Submit the export and store the downloaded CSV only in an approved location.

Extraction exports campaign form data; it is not a complete backup of users, captures, recordings, or telephony history. A downloaded file is an additional copy of personal data and must be managed accordingly. A **No tables to export** message means no eligible configured tables were resolved, not necessarily that the campaign never had data.

For retention policies and deletion boundaries, see [retention](retention-and-activity-log.md). For missing or inconsistent call history, see [troubleshooting](../operations/troubleshooting.md).

Source: [record-list controller](../../app/Http/Controllers/Admin/RecordsListController.php), [Data Master controller](../../app/Http/Controllers/Admin/DataMasterController.php), [capture controller](../../app/Http/Controllers/Admin/CaptureRecordsController.php), [extraction controller](../../app/Http/Controllers/Admin/ExtractionController.php), [CSV service](../../app/Services/ExtractionService.php).

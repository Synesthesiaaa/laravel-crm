# Forms, field logic, and Agent Screen configuration

These are three related configuration surfaces, not interchangeable editors. **Super Admin → Forms** defines campaign forms. **Administration → Field Logic** defines their fields. **Super Admin → Agent Screen Configuration** defines capture fields and webforms used by the Agent Screen.

## Define a campaign form

As Super Admin, open **Forms**, select the campaign, and create the form with a campaign-unique form code, display name, storage table name, order, and the offered presentation settings. Choose identifiers with the maintainer; table names are storage configuration rather than agent-facing labels. Save, then open **Field Logic** for that form. Form deletion is deactivation, not deletion of historical storage rows.

The submission service can create or extend per-form storage tables/columns as part of saving a submission. Configuration has real storage consequences. Do not rename storage identifiers, change types, or repoint a live form casually. Existing data and other integrations need review first.

## Add and edit fields

Field creation and update require **Admin or Super Admin**, even though Team Leaders can open the Field Logic page. Select the correct form, then set:

| Setting | Effect |
| --- | --- |
| Field name | Technical key, using letters, numbers, or underscores. |
| Label | Text agents see. Prefer a clear business description. |
| Type | Text, textarea, number, date, select, multiselect, or percentage. |
| Required | Enables server validation for that configured field. |
| Order and width | Controls placement and full/half/third layout. |
| Options | Select and multiselect need at least one option; enter one per line. |
| Sale amount | Only retained for number fields; influences legacy dashboard sales attribution. |

Save and review the form as an agent would see it. Use stable option values: changing spelling can affect historical interpretation and custom sales tags. Field deletion removes the definition; do not assume it deletes the corresponding historical storage column or anonymizes existing values.

## Conditional visibility

Choose a sibling controlling field, an operator (**equals**, **not_equals**, **in**, **not_in**), and comparison values. Incomplete visibility settings are normalized away. The controller accepts newline/comma-separated comparison values and removes duplicate values.

Visibility is an interface rule, not an authorization boundary. The ordinary submission request still validates required fields from their definitions without making requiredness conditional on visibility. Avoid a configuration where an invisible field is required but cannot be completed. Validate each branch in a designated test environment before release.

## Configure sales attribution

The Forms editor supports per-form custom sales attribution. A rule can count all form submissions, a numeric amount marker, or configured matching tags. Conditions use an any-match test. An optional amount field supplies the amount; absence of an amount can still produce a count with zero amount. Removing the last custom rule returns persisted sales configuration to its fallback behavior. See [report definitions](../user-guide/reports.md) before comparing old and new numbers.

## Configure captures and webforms

**Agent Screen Configuration** selects the campaign's capture fields and optional webform. These are reviewed in **Capture Records**, not Data Master. Confirm which surface agents should use, which values are required, and whether the Agent Screen feature is enabled. Empty capture configuration produces an explicit empty state on the Agent Screen.

For each capture field, use a stable unique field key and clear label, select its type, requiredness, order/width, and any visibility rule. Where mapped to a VICIdial field, review its direction: **get** reads from the source, **post** sends captured values back, and **both** supports both directions. Outgoing fields must also be writable in the configured VICIdial field catalog. Local capture save can queue a separate lead-field synchronization job, so success does not prove the remote lead already contains the new values.

To enable the campaign's agent capture webform, select an active Form belonging to the same campaign and save the webform choice. The screen supplies a generated VICIdial Web Form URL with lead/phone and readable mapped-field placeholders for the telephony administrator to configure. The selected Form provides the webform context; the capture fields and save endpoint still use Agent Screen capture records. Opening a valid agent-webform also updates the CRM session campaign to that webform's campaign. Do not treat the URL as an anonymous public intake form: its route requires CRM authentication and Agent Screen access.

Known authorization edge: Field Logic deletion currently inherits the Team Leader-or-higher route group without the Admin-only Form Request used by create/update. The role guide documents this behavior; it is not a recommended delegation policy.

Source: [forms controller](../../app/Http/Controllers/Admin/FormsController.php), [field controller](../../app/Http/Controllers/Admin/FieldLogicController.php), [field request](../../app/Http/Requests/Admin/StoreFieldLogicRequest.php), [submission validation](../../app/Http/Requests/FormSubmissionRequest.php), [storage service](../../app/Services/FormSubmissionService.php), [capture configuration controller](../../app/Http/Controllers/Admin/AgentScreenController.php), [webform service](../../app/Services/AgentCaptureWebformService.php), [capture save/synchronization](../../app/Http/Controllers/Api/AgentCaptureController.php).

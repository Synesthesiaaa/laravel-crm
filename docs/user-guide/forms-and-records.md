# Forms, capture details, and records

## Choose the correct save action

| Information | Where to enter it | Where management reviews it |
| --- | --- | --- |
| Campaign-specific business form | **Campaign Forms → form name** | **Administration → Data Master**; submission activity in **Records List** |
| Agent Screen capture | **Agent Screen → Capture Details → Save Record** | **Administration → Capture Records** |
| Call outcome | Call disposition workflow | **Call History**, **Disposition Records**, telephony reporting as available |

These saves serve different purposes. Saving a form or capture does not by itself establish that a call ended or that its disposition reached VICIdial.

## Submit a campaign form

1. Expand **Campaign Forms** and choose the form named in your work instruction. Available names come from the current campaign configuration.
2. Check the form and campaign heading, date, lead ID, phone number, and any prefilled values.
3. Complete required fields. Choose dropdown values exactly as provided. Multi-select fields accept several configured choices. Enter numeric values in numeric fields and an appropriate date in date fields.
4. Review the information, then submit using the form's save control.
5. Wait for **Record saved successfully.** If validation errors appear, correct those fields and submit again. A failed submission is not a saved record.

For the formal **Agent** role, the server forces the submission date to the current application date. It also uses the authenticated user's identity for agent attribution and generates the request ID. Do not rely on changing these values in the browser. Optional empty fields are generally stored as null; multi-select choices are stored as a normalized list.

Visibility rules can show or hide fields. Required validation still comes from the configured field rules: a hidden required field can cause an error if administrators configure conflicting rules. Report the form, controlling selection, and field label rather than inventing a value.

## Save an Agent Screen capture

Confirm the current **Lead Information**, complete **Capture Details**, and choose **Save Record**. If the page says fields are not configured, a Super Admin must configure the campaign's Agent Screen. A configured embedded webform is a separate capture surface; follow the form displayed rather than assuming it matches a Campaign Form.

## Find information again

**Call History** opens the calling history workspace. Agents receive a personal view; Team Leader and higher roles receive broader campaign context. It is not the Data Master editor. Managers use **Records List** for submission activity and its Calls tab, **Data Master** for campaign form data, and **Capture Records** for capture submissions. Record IDs from different stores are not interchangeable.

Before repeating a save after a timeout, search the relevant record store or ask a manager to check. No general duplicate-prevention promise applies to repeated manual submissions.

Source: [submission request](../../app/Http/Requests/FormSubmissionRequest.php), [submission service](../../app/Services/FormSubmissionService.php), [form controller](../../app/Http/Controllers/FormController.php), [records controller](../../app/Http/Controllers/RecordsController.php), [capture controller](../../app/Http/Controllers/Admin/CaptureRecordsController.php).

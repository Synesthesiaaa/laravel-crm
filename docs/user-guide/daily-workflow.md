# A normal working day

## Before the first customer

1. Sign in using the CRM account provided to you; see [first login](../getting-started/first-login.md).
2. Check the campaign name. A CRM campaign groups forms and reporting context. It is different from a VICIdial campaign used by the phone system.
3. Open **Dashboard** to review the current campaign information. Treat form sales figures and telephony figures as different measures; see [reports](reports.md).
4. Open the floating **Phone** button if you need calling. Check **VD Login** and **Phone Login** against the account details supplied by your administrator. Start the session using its visible login control and wait for confirmation. **Online** describes session status; it does not prove a particular call has connected.
5. Open **Agent Screen** if enabled. Confirm the phone number, lead ID, and prefilled information before speaking or saving.

Do not share accounts or assume signing into the CRM also establishes a usable telephony session. An administrator must configure the campaign server, credentials, and relevant features.

## For each customer

1. Identify the customer and verify the lead context. Use the **Lead** tool when enabled; do not overwrite another customer's details to move faster.
2. Use the dial control or accept your configured incoming workflow. Follow [calls and dispositions](calls-and-dispositions.md).
3. Complete the relevant information: **Capture Details → Save Record** stores an Agent Screen capture; **Campaign Forms** stores a campaign form submission. They are separate record types.
4. End the call when appropriate, choose the accurate outcome, and save the disposition. Complete wrap-up before dialing again.
5. If a return call was agreed, schedule and verify it with the [Callback Scheduler](callbacks.md). A note or disposition alone is not proof of a scheduled callback.
6. Check the success message and, when needed, the relevant records page. Avoid repeated saves after an uncertain network response: first check whether a record already exists.

## Breaks and end of shift

Use **Attendance** to start and end an available attendance status. This is separate from pausing the VICIdial session. If your process requires both, do both. Only one custom attendance status can be open at a time for the current day.

Before leaving, finish the active call, save its outcome and required records, end your attendance status, and log out of the phone session using its control. Sign out of the CRM using the account menu. Minimizing the Phone widget keeps its session running; it is not logout. CRM logout attempts to close an open current-day attendance segment.

If a step fails, preserve the error text and consult [troubleshooting](../operations/troubleshooting.md). Do not repeatedly dial, resubmit forms, or ask a colleague to use your session as a workaround.

Source: [Phone widget](../../resources/views/partials/phone-widget.blade.php), [Agent Screen](../../resources/views/agent/index.blade.php), [attendance service](../../app/Services/AttendanceStatusService.php), [form controller](../../app/Http/Controllers/FormController.php).

# Calls and dispositions

## Prepare the phone session

Open the floating **Phone** widget, check **VD Login** and **Phone Login**, and use the visible login action. Wait for its confirmation. The widget embeds the VICIdial session; it may show additional controls supplied by that external system. Browser media permission, server configuration, credentials, and network access must all be correct. Minimizing the widget leaves the session running.

On **Agent Screen**, confirm the customer phone number and lead ID. The primary controls show **Ready**, **Dialing…**, **Ringing…**, **Connected**, or **Wrap-up**. The call timer is an interface aid; use stored call/report information for historical review.

## Complete one call

1. From **Ready**, enter or verify the phone number and select the phone icon labeled **Dial phone number**.
2. Watch the state and any error message. A request being sent does not prove the customer answered.
3. During the conversation, capture the required information and save the appropriate record.
4. Select **Hang up call** when the conversation is finished, if it is still active.
5. Complete the disposition workflow with the outcome that actually occurred, adding useful remarks where offered.
6. Wait for the disposition save to succeed and wrap-up to clear before the next call. Dialing can be blocked while a disposition is pending.

Use your campaign's agreed outcomes. Labels and available codes are configurable; this guide does not prescribe universal sale, no-answer, or callback codes. Saving an unrelated code merely to unblock dialing makes records and reports misleading.

## Advanced tools

Open a tab in **Call tools** only when needed:

| Tab | Purpose and care |
| --- | --- |
| **Groups** | Manage configured inbound groups; follow campaign routing instructions. |
| **Transfer** | Offers configured transfer and park operations. Confirm the destination and expected handoff before acting. |
| **Recording** | Start, stop, check, or look up recordings when enabled. A visible control is not proof a recording exists or is playable. |
| **DTMF** | Send keypad tones for the current call. |
| **Callback** | Schedule, remove, or inspect a lead callback; see [callbacks](callbacks.md). |
| **Lead** | Search and work with lead information; see [campaigns and leads](campaigns-and-leads.md). |

Feature switches can hide controls and reject their server requests. Predictive dialing also depends on campaign settings, queued lead availability, and the telephony integration; do not assume it works merely because the application contains predictive-dialing code.

If hangup, disposition, or connection status disagrees with the external phone session, stop further actions, keep the lead ID and time, and contact the supervisor. Repeated hangup, transfer, or dial clicks can have real calling effects. See [troubleshooting](../operations/troubleshooting.md).

Source: [Agent Screen](../../resources/views/agent/index.blade.php), [Phone widget](../../resources/views/partials/phone-widget.blade.php), [routes and feature gates](../../routes/web.php), [disposition controller](../../app/Http/Controllers/Api/SaveDispositionController.php), [disposition service](../../app/Services/DispositionService.php).

# Schedule and verify callbacks

A callback asks the phone system to contact a lead later. It is separate from a CRM note, a campaign form, and the current call's disposition. The callback feature and a working VICIdial connection must be available.

1. Open **Agent Screen** and verify the **Lead ID**. The schedule, removal, and lookup requests require a lead ID.
2. Open **Call tools → Callback**. The panel is titled **Callback Scheduler**.
3. Choose the local date and time using the date/time control. Confirm the intended timezone with your supervisor, especially for customers in another region; the UI does not provide a timezone selector in this panel.
4. Choose **ANYONE** for a callback available to the configured pool, or **USERONLY** for a specified callback user. For USERONLY, enter the appropriate phone-system user, not an assumed CRM display name.
5. Add concise comments explaining the agreed purpose. Comments are limited to 255 characters by the endpoint.
6. Select **Schedule** and read the result. Select **Info** to inspect the stored callback information rather than assuming the click succeeded.

Use **Remove** only after confirming the correct lead and that the callback is no longer required. Check **Info** again when you need to verify the result. Schedule and Remove send requests to the telephony integration and can fail even when the CRM page loads normally.

The API supports callback types ANYONE and USERONLY and defaults the callback status to CALLBK. Actual scheduling semantics, eligible users, and timezone interpretation depend on the configured VICIdial system. This documentation does not claim a reminder notification, automatic redial at an exact instant, or guaranteed callback delivery.

After scheduling, finish the current call's wrap-up and disposition according to your campaign process. If the result is uncertain, record the requested time and lead ID for a supervisor; avoid creating repeated callbacks blindly.

Source: [callback panel](../../resources/views/agent/partials/callback-form.blade.php), [callback controller](../../app/Http/Controllers/Api/CallbackController.php), [callback service](../../app/Services/Telephony/CallbackService.php).

# User Access

**Who:** Super Admin. **Navigation:** **Super Admin → User Access** (`/admin/users`). Team Leaders and Admins do not receive user-management access from their titles alone.

## Create a staff account

1. Open User Access and choose the create-user action.
2. Enter a unique username and the person's full name. Choose one formal role: **Agent**, **Team Leader**, **Admin**, or **Super Admin**.
3. Enter and confirm the initial password. The request requires at least eight characters with uppercase, lowercase, and a number. Transfer credentials through your approved secure channel.
4. Set telephony information only from verified provisioning details: VICIdial user/password, extension, SIP password, default campaign, automatic VICIdial login preference, blended preference, and default inbound groups as appropriate.
5. Save and confirm **User created.** Arrange a safe first-login check with the user; never ask them to expose their password in screenshots.

The CRM password, VICIdial password, and SIP password serve different systems. An extension is not a CRM username. A default campaign is a preference/context setting, not proof of campaign membership enforcement.

## Edit or remove access

Find the account in the paginated list, open its edit action, change the required details, and save. On update, blank CRM/VICIdial/SIP password fields preserve the existing passwords; they do not clear credentials. Recheck default campaign and login preferences after an edit.

Delete only the correct account after arranging handover and confirming the operational effect. The service prevents deleting your own account and uses the User model's soft deletion. There is no account-restore UI promised here. Deleting a CRM account is not documented as removing the corresponding account from VICIdial or revoking every external credential.

## Provisioning limits

The service attempts VICIdial credential synchronization after CRM creation/update, but catches synchronization failures so they do not block the CRM success message. **User created** or **User updated** therefore does not prove remote provisioning succeeded. If the user cannot establish the phone session, have an administrator inspect the integration rather than repeatedly resetting the CRM password.

Role enforcement uses the `users.role` value and explicit route/request checks. Seeded Spatie permissions are not a substitute for those checks. For capabilities and known uneven write authorization, see [roles](../roles-and-permissions.md).

Source: [user controller](../../app/Http/Controllers/Admin/UsersController.php), [creation validation](../../app/Http/Requests/Admin/StoreUserRequest.php), [update validation](../../app/Http/Requests/Admin/UpdateUserRequest.php), [user service](../../app/Services/UserService.php), [user model](../../app/Models/User.php).

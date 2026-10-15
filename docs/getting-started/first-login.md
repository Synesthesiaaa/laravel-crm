# First login

You need an account supplied by a Super Admin and the correct campaign name. Accounts use a **username**, not an email address. There is no public registration or self-service password-reset flow in current routes.

1. Open `/login` at your organization's CRM address.
2. Enter username and password, choose the intended campaign, and submit.
3. If another browser session is detected, review the confirmation. Confirming continues sign-in and removes other database sessions; cancelling returns to login. Confirmation expires after five minutes.
4. Check the campaign on the dashboard before entering information. A missing/unrecognized campaign falls back to the first configured campaign; it is not a membership denial screen.
5. Open tools visible for your role. Unavailable Agent Screen can mean the global feature toggle is off, even though login succeeded.
6. At the end of work, finish the interaction, save the outcome, and use Sign out. Logout closes attendance status locally, completes local active call state, marks the local VICIdial session logged out, and invalidates the browser session. Confirm the external phone/session separately; local logout does not prove every remote session ended.

Repeated bad credentials are limited by username/IP attempts and the login route's IP limit. Wait for the shown retry time. For a session-expired message, reopen login and retry with a fresh page. Ask a Super Admin to check role, username, sessions, and campaign configuration when access remains wrong.

**Development only:** the fresh seed account is `admin` / `password`. Change it immediately through User Access. Never expose a seeded environment with that credential.

There is no verified user-to-campaign membership assignment system. Use the operating campaign instructed by your organization, and see [roles and permissions](../roles-and-permissions.md) for actual boundaries.

Sources: [LoginController](../../app/Http/Controllers/Auth/LoginController.php), [LoginRequest](../../app/Http/Requests/Auth/LoginRequest.php), [AuthService](../../app/Services/AuthService.php), [CampaignService](../../app/Services/CampaignService.php). Next: [user guides](../user-guide/README.md).

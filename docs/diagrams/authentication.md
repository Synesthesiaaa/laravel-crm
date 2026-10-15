# Browser authentication

```mermaid
flowchart TD
    Login[POST login with CSRF] --> Limits[Route and username-IP limiters]
    Limits --> Validate[Validate credentials]
    Validate -->|Invalid| Errors[Validation errors]
    Validate -->|Valid| Other{Other database sessions?}
    Other -->|Yes| Pending[Store pending login for five minutes]
    Pending --> Confirm[POST pending confirmation with CSRF]
    Confirm --> Expiry{Still valid and user exists?}
    Expiry -->|No| Retry[Return to login]
    Expiry -->|Yes| Final[Sign in and delete other sessions]
    Other -->|No| Final
    Final --> Attendance[Attendance and security event]
    Attendance --> Campaign[Resolve campaign and telephony bootstrap]
    Campaign --> Regenerate[Regenerate session]
    Regenerate --> Dashboard[Intended page or dashboard]
```

LoginController validates credentials before offering session takeover. Confirmation is time-limited. Finalization disables remember-me, logs attendance, resolves campaign, stores bootstrap data and regenerates the session. This diagram excludes Sanctum bearer authentication, which is a separate route boundary. Source: LoginController, LoginRequest and AuthService.

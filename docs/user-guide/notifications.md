# Notifications and supervisor messages

Open the notification control in the application header to review recent items. Depending on your activity and role, the feed combines stored supervisor messages with derived call history, capture activity, your attendance events, and daily performance items.

1. Open the notification list and read the title, time, and context.
2. Open an item for its detail when available. Verify the customer or record context before taking action elsewhere.
3. Use the read controls, including mark all read where shown, to acknowledge items you have reviewed.
4. If a supervisor message requires work, complete that work in the relevant screen. Marking a notification read only changes its read state.

The feed is a recent activity view with configurable time and visible-item limits, not a complete audit archive. The unread count can exceed the currently visible list. Items may be derived from records rather than being an email or phone-system message. Daily performance items from earlier dates are treated as read by the feed.

Supervisor notifications can target a user, a phone-system user group, or a campaign. After successful VICIdial delivery, the controller mirrors USER notifications to matching local users and CAMPAIGN notifications to users whose default campaign matches; USER_GROUP has no local mirror in that implementation. Therefore a missing CRM notification is not by itself proof that VICIdial rejected a group message, and a local notification is not proof the user acted on it.

If a message appears late, check its time and your connection. Ask the sender to verify the intended recipient rather than repeatedly broadcasting messages. The feed is not documented as an alarm or guaranteed callback reminder system.

Source: [notification service](../../app/Services/NotificationService.php), [read service](../../app/Services/Notifications/NotificationReadService.php), [supervisor notification delivery](../../app/Http/Controllers/Api/SupervisorTelephonyController.php), [notification UI](../../resources/views/layouts/app.blade.php).

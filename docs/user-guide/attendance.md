# Attendance and breaks

Open **Attendance** in the main sidebar. This page shows your own attendance events for the selected date. Login and logout events are distinct from custom statuses such as an organization's lunch or break statuses.

## Start and finish a status

1. Review the current status before choosing an action.
2. Start the appropriate available attendance status when your break or activity begins.
3. End that status when you return.
4. Check the updated state and event history. End the current status before starting another; the service rejects overlapping open custom statuses.

Only active configured status types can be started. A Super Admin manages their codes, labels, and ordering in **Super Admin → Attendance Statuses**. Names in your deployment may differ from examples here.

Attendance statuses do not automatically mean that VICIdial is paused. If your procedure requires a phone pause, use the Phone widget or your approved telephony controls separately. Likewise, a disconnected phone session is not evidence that the CRM recorded a break.

## Review a date

Use the page's date filter to review a specific day. The personal controller loads up to 50 events for that user/date. A short list is not a complete payroll calculation, and event counts are not hours worked. Report missing or incorrect events with the date, event, and expected time; there is no self-service attendance correction workflow documented by these routes.

Team Leaders, Admins, and Super Admins can open **Administration → Staff Attendance** for staff history and realtime attendance. The history is capped at 100 returned events, and displayed summary counts describe that loaded set. Narrow filters when investigating a busy day.

## End-of-day limitations

CRM logout attempts to close an open custom status for the current application day. Open-status detection only looks at today's events. An overnight segment is therefore not a general supported elapsed-time calculation; ask management how overnight shifts should be recorded. Closing a browser or losing connectivity is not a dependable logout action.

Source: [personal controller](../../app/Http/Controllers/AttendanceController.php), [status service](../../app/Services/AttendanceStatusService.php), [staff controller](../../app/Http/Controllers/Admin/AttendanceLogsController.php), [attendance view](../../resources/views/attendance/index.blade.php).

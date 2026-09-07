## Context

The shared application layout owns an Alpine `notificationDropdown` that calls `GET /api/notifications`, combines Laravel database notifications with `crm_call_history`, and marks all visible items read. The backend currently concatenates database notifications before history instead of ordering the combined feed, uses a cache list for history read IDs, formats titles from `form_type` and `campaign_code`, and has no attendance or daily sales provider. The frontend loads the list only the first time the panel opens, clears the list on request failure, gives notification rows a pointer cursor without a click action, and has no Alpine destroy hook even though `#main-layout` is replaced during soft navigation.

The dashboard already has the authoritative selected-range calculation in `DashboardStatsService::getSalesKpisForCampaign()`. Its default range is the current application-timezone date from 06:00 inclusive to 18:00 exclusive, and its result contains team sales count/amount, top agent, per-form totals, and a complete leaderboard. Campaign amount visibility is stored in the dashboard layout. Attendance events are stored in `attendance_logs`, with user-facing custom labels in `attendance_status_types`.

The current worktree has unrelated in-progress report/OpenSpec changes. This change must not modify or reformat those files except where an implementation task explicitly intersects the shared notification shell.

## Goals / Non-Goals

**Goals:**

- Present one user-scoped feed for supervisor messages, personal call/form history, personal attendance, and a live current-day performance summary.
- Keep notification performance values identical to the dashboard by consuming its existing aggregation and layout visibility rules.
- Show human-facing names and labels, never raw campaign, form, attendance, class, VICIdial, or recipient codes as display copy.
- Make each row operable by mouse, touch, and keyboard and show useful details in one accessible shared modal.
- Make read state durable, ordering deterministic, refresh behavior current, and failures visible and recoverable.
- Preserve supervisor message delivery and make HTTP/database behavior useful when Reverb is unavailable.

**Non-Goals:**

- Email, SMS, push, or mobile notifications.
- A standalone full-page notification inbox, search, filtering, or infinite history.
- Historical daily-performance cards for prior dates; this change exposes one live card for the active campaign and current application-timezone day.
- Changing dashboard sales attribution, Top Agent ranking, default business hours, or amount-visibility policy.
- Changing attendance rules, supervisor recipient selection, call-history capture, or repairing unrelated VICIdial report errors.

## Decisions

### 1. Build a derived feed over authoritative domain records

`NotificationService` will become the façade for four focused providers: Laravel database notifications, personal CRM call/form history, personal attendance events, and current-day performance. Each provider returns the same immutable item shape with a stable key, category, display copy, occurrence/update timestamp, severity, read state, and detail capability. The façade merges candidates, deduplicates by key, globally orders them newest-first with a stable key tie-breaker, applies the configured page limit, and separately calculates the unread count for the bounded notification window.

The daily-performance item is regenerated from current data and uses one stable key per user, active campaign, and local date. Its `updated_at` is the calculation time so it remains the first live summary, but reading it once keeps it read for that date; normal metric refreshes do not repeatedly mark it unread. A new date or campaign creates a new key.

Alternative considered: create Laravel database notification rows for every attendance and form event plus a scheduled daily summary. This would provide native read state and broadcasting, but duplicates authoritative records, requires fan-out/backfill and idempotency, and delays in-progress daily information until a scheduler runs. The derived feed is smaller and keeps the requested data live.

### 2. Persist read receipts for derived items

Add `notification_read_states` with `user_id`, `item_key`, `read_at`, timestamps, a unique `(user_id, item_key)` constraint, and an index suitable for user/read lookups. Existing Laravel database notifications continue to use their native `read_at`; call/form, attendance, and daily summary keys use the new table. `NotificationReadService` hides the storage distinction, supports one-item and mark-all operations, and performs idempotent upserts. Read states older than 90 days are pruned by the existing scheduler pattern.

No domain records are altered when a notification is read. Existing cache-based history read IDs may be consulted once during a short compatibility period or migrated opportunistically, but cache is no longer authoritative after deployment.

Alternative considered: keep cache read IDs. It avoids a migration but loses state after cache clearing/expiry, cannot safely represent multiple source types, and makes read behavior inconsistent across deployments.

### 3. Reuse dashboard KPI and identity resolution

Extract the dashboard's default and request-selected sales range rules from `DashboardController` into a focused `DashboardSalesRangeService`. Both the dashboard controller and `DailyPerformanceNotificationProvider` use that service so the current-date 06:00–18:00 application-timezone boundary is not duplicated. The provider then calls `DashboardStatsService::getSalesKpisForCampaign()` with the resolved range. It derives the current user's row by a shared, case-insensitive agent alias resolver over `full_name`, `name`, `username`, and `vici_user`; it does not issue a second sales query or define a second ranking algorithm. The detail response includes personal sales count/amount, team sales count/amount, Top Agent name/count/amount, per-form totals, and the existing leaderboard. Amount fields are omitted from display data whenever the active campaign's dashboard layout hides the corresponding monetary information.

`NotificationLabelResolver` resolves campaign codes to `Campaign::name`, form types to active `Form::name`, attendance status IDs to `AttendanceStatusType::label`, and mapped agent identifiers to the user's preferred display name. Unresolved internal codes use neutral copy such as “Campaign,” “Form activity,” or “Agent” rather than exposing the raw value. Status values are converted to readable labels where they are intended as user-facing status text.

Alternative considered: reproduce the dashboard queries inside `NotificationService`. That risks immediate drift in custom sales rules, time boundaries, amount visibility, and Top Agent ordering.

### 4. Use a compact list plus one shared details modal

The bell opens a responsive popover on desktop and a viewport-safe sheet-like panel on small screens, preserving the existing design tokens and icon set. A compact “Today’s performance” row leads with the user's result and indicates that team totals and Top Agent are available. Chronological rows show category, title, short message, time, and unread state. Every row is a native button with a visible focus ring and at least a 44px interaction height; click, Enter, or Space marks the item read and opens the modal.

The shared modal renders category-specific sections:

- Performance: campaign name, date/business range, personal count/amount, team count/amount, Top Agent, per-form totals, and leaderboard.
- Attendance: human status/action label, date/time, current/open state, and paired duration when it can be determined reliably.
- Call/form activity: campaign name, form name, readable status, timestamp, record/lead context, phone number, and remarks already authorized for the user.
- Supervisor: message, sender display name when resolvable, sent time, and recipient label without recipient codes.

The modal uses the existing modal store/component, moves focus to its heading or first control when opened, traps focus according to the existing component behavior, closes with Escape or its visible close button, and restores focus to the originating notification row. Decorative icons are hidden from assistive technology; unread and severity are communicated with text/semantics in addition to color.

Because the shared header uses `backdrop-filter`, the modal markup is wrapped in Alpine's `x-teleport="body"`. This preserves the notification component's reactive scope while keeping the fixed modal backdrop viewport-relative instead of contained by the header.

Alternative considered: navigate every item to an existing page. Some sources have no appropriate destination, and navigation would lose the requested quick-view behavior. The modal can still include a named-route link when a safe relevant page exists.

### 5. Add explicit index, detail, and read contracts

Keep `GET /api/notifications` for the panel and return normalized items, exact unread count for the bounded window, `has_more`, and server refresh time. Add an authenticated detail endpoint and an authenticated single-item read endpoint, while retaining `POST /api/notifications/read-all`. Item keys are opaque to the UI beyond equality and submission back to the server. The server resolves a key back to a source and re-applies ownership and active-campaign scope before returning details or accepting a read; a guessed ID must never disclose another user's attendance, history, supervisor notification, or an inaccessible campaign summary.

The index response does not expose raw identifiers as display fields. Internal source keys required for server resolution are not interpolated into copy. Invalid, stale, or unauthorized keys return a non-disclosing 404.

### 6. Refresh safely across polling, realtime, and soft navigation

The panel performs a fresh, deduplicated request every time it opens. While the document is visible, a bounded interval refreshes the unread summary; when the panel is open it refreshes the list. Supervisor broadcasts prepend/update by stable key and trigger a reconciliation fetch. Attendance success in the same browser triggers an immediate refresh, while form and other non-user-specific activity remains covered by open-time refresh and polling. Requests use an in-flight guard or cancellation so late responses cannot overwrite newer state.

On refresh failure, the component retains the last successful items, marks them stale, shows an inline `role="alert"` recovery message with Retry, and does not display “No notifications.” Empty state is shown only after a successful empty response. Reverb connection failure does not block HTTP/database notifications; polling remains the fallback.

`notificationDropdown` gains an Alpine `destroy()` hook that clears timers, cancels outstanding work, and invokes the active Echo unsubscribe function before `#main-layout` replacement. Echo subscription deduplication must include ownership/handler lifecycle or replace the old subscription rather than returning a teardown bound to a stale Alpine instance.

## Risks / Trade-offs

- **Daily summary queries could make frequent polling expensive** → Cache the shared dashboard calculation at the existing service layer, use a lightweight unread-summary path, calculate full details only when the panel/detail is requested, and prohibit overlapping requests.
- **Agent strings may not map cleanly to a user** → Centralize case-insensitive alias matching, test all supported aliases, and use neutral display fallbacks without merging two users.
- **A live daily card can change after it is read** → Keep one read key per campaign/day and label it as live/updated; only the next day or campaign produces a new unread item.
- **Derived items can disappear when source data is retained/deleted** → Detail lookup returns a recoverable “no longer available” state and stale read receipts are pruned.
- **Amount visibility can differ by campaign** → Resolve layout controls for every response and omit hidden amounts from preview and modal data rather than merely hiding them with CSS.
- **Mixed source counts can grow** → Limit the visible page to 25, bound derived activity/unread calculations to 30 days, cap badge presentation at `99+`, and return `has_more` without implementing infinite history.
- **Broadcast service outages can still produce noisy logs** → Treat broadcast as an enhancement; preserve database delivery, avoid surfacing transport internals to users, and verify the polling fallback. Infrastructure repair for Reverb itself remains outside this change.
- **Existing active OpenSpec/dashboard work may alter KPI details** → Depend on the public dashboard KPI result and write consistency tests instead of copying current implementation internals.

## Migration Plan

1. Add the `notification_read_states` table and model without removing existing notification storage or cache keys.
2. Deploy backend item normalization, label resolution, provider aggregation, detail/read authorization, and compatibility with the current frontend response fields.
3. Deploy the panel/modal component, refresh lifecycle, and error states; enable durable reads for derived items.
4. Retain old cache keys through one release, then stop reading them after durable behavior is verified. Prune durable read rows older than 90 days through the scheduler.
5. Rollback is additive: revert routes/services/UI first, then drop the new table only if its read receipts are no longer needed. Domain activity and Laravel database notifications are untouched.

## Open Questions

No blocking product questions remain for planning. The plan assumes “daily” means the current application-timezone date using the dashboard's existing default 06:00–18:00 business range, that all authenticated users who can already see the dashboard may see the same campaign team totals/leaderboard, and that the notification modal must honor dashboard amount-visibility settings.

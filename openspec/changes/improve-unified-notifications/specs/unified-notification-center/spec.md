## ADDED Requirements

### Requirement: Authenticated users receive a unified scoped notification feed
The system SHALL return one normalized notification feed containing the authenticated user's supervisor messages, active-campaign call/form activity (including campaign capture-form submissions), attendance activity, and one daily performance summary for each application-timezone date in the bounded activity window. The current date SHALL remain live while prior dates SHALL be historical snapshots calculated from their own business-day range. Personal activity SHALL be matched to the authenticated user through an exact activity owner when available and otherwise through supported agent aliases, attendance SHALL be limited to that user's records, campaign activity SHALL be limited to the active accessible campaign, and the system SHALL NOT expose another user's private activity through item IDs or detail requests.

#### Scenario: Mixed activity is available
- **WHEN** the authenticated user has a supervisor message, personal form history, an attendance event, and a current-day performance summary
- **THEN** the notification response contains all four categories in the normalized item contract
- **AND** each item can be resolved only by that authenticated user in its authorized campaign scope

#### Scenario: Daily performance has historical dates
- **WHEN** the authenticated user requests the feed on a date with activity on multiple dates in the bounded window
- **THEN** the response contains a stable performance item for the current date and each historical date in the window
- **AND** opening a historical item calculates details for that item's date rather than silently returning the current day's metrics

#### Scenario: Campaign form submissions are recorded
- **WHEN** the authenticated user submits a standard campaign form or campaign capture form
- **THEN** a user-scoped call/form notification is available without requiring a duplicate Laravel notification row
- **AND** another user cannot resolve the item's detail or read key

#### Scenario: Another user's source ID is requested
- **WHEN** an authenticated user requests notification details for an attendance, history, or database-notification record owned by another user
- **THEN** the system returns a non-disclosing not-found response

### Requirement: Mixed notification items are stable, deduplicated, and correctly ordered
Every notification item SHALL have a stable source-qualified key and occurrence/update timestamp. The service SHALL deduplicate equal keys, order current live performance, historical performance, and activity deterministically newest-first, limit the visible response to 25 items, calculate unread count across the bounded 30-day notification window, and indicate when additional items exist.

#### Scenario: A newer history item follows an older supervisor item
- **WHEN** providers return an older supervisor message and a newer call/form item
- **THEN** the newer call/form item appears first after the current-date live performance item
- **AND** provider concatenation order does not override chronological order

#### Scenario: Realtime item is reconciled with REST
- **WHEN** a broadcast item is already present and the next HTTP response contains the same stable key
- **THEN** the panel contains one copy with the server-authoritative data and read state

### Requirement: Derived notification read state is durable and item-specific
The system SHALL persist per-user read state for call/form, attendance, and daily-performance items in durable database storage, SHALL use Laravel database notification read state for database notifications, and SHALL expose idempotent single-item and mark-all-read operations. Historical daily-performance items SHALL not inflate the unread badge merely because they are generated during a later request. Clearing application cache SHALL NOT make a read derived item unread again.

#### Scenario: User opens one notification
- **WHEN** the user activates an unread notification row
- **THEN** only that item is durably marked read before or while its details open
- **AND** the unread badge decreases without becoming negative

#### Scenario: Cache is cleared after reading history
- **WHEN** a derived call/form item has been marked read and application cache is cleared
- **THEN** the item remains read in subsequent notification responses

#### Scenario: User marks all visible notifications read
- **WHEN** the user activates Mark all read
- **THEN** every currently addressable unread item in the bounded feed is marked read idempotently
- **AND** the returned unread count is zero for that feed state

### Requirement: Notification copy uses human-facing labels
Notification previews and details SHALL resolve configured campaign names, form names, attendance-status labels, readable statuses, sender names, and agent display names. They SHALL NOT render raw campaign codes, form codes/types, attendance codes, Laravel notification class names, recipient IDs, VICIdial users, or other internal configuration/routing identifiers as user-facing copy; an unresolved identifier SHALL use neutral fallback copy.

#### Scenario: Configured labels exist
- **WHEN** history contains campaign code `mbsales`, form type `ezycash`, and the configured names are `MB Sales` and `EzyCash Application`
- **THEN** the notification displays `MB Sales` and `EzyCash Application`
- **AND** it does not display `mbsales` or `ezycash`

#### Scenario: A label lookup fails
- **WHEN** a referenced campaign, form, attendance type, sender, or agent cannot be resolved
- **THEN** the notification uses a neutral user-facing fallback
- **AND** the raw code or identifier is not substituted into visible text

### Requirement: Notification rows open accessible category details
Each notification row SHALL be a semantic button operable by pointer, touch, Enter, and Space. Activating it SHALL mark the item read and open a shared modal containing category-appropriate details, with a visible close control, Escape dismissal, visible focus styling, modal focus management, focus restoration to the originating row, and non-color text or semantics for unread/severity state.

#### Scenario: Performance notification is activated
- **WHEN** the user activates a current-day or historical performance row
- **THEN** the modal shows campaign name, date and business range, personal sales, team totals, Top Agent, per-form totals, and leaderboard subject to amount visibility

#### Scenario: Performance notification compares monthly totals
- **WHEN** the user opens performance details for any available date
- **THEN** the modal shows current-period and equivalent previous-month sales counts, the count change, and the comparison periods
- **AND** it shows current-period and equivalent previous-month sales amounts and the amount change when dashboard amount visibility permits monetary totals
- **AND** zero-baseline comparisons use a readable new-activity state instead of an infinite percentage

#### Scenario: Attendance notification is activated
- **WHEN** the user activates an attendance row
- **THEN** the modal shows the attendance label, action, timestamp, current/open state, and reliable paired duration when available

#### Scenario: Call or form notification is activated
- **WHEN** the user activates a call/form row
- **THEN** the modal shows resolved campaign/form labels, readable status, time, and authorized record context without raw configuration codes

#### Scenario: Keyboard user closes details
- **WHEN** a keyboard user opens a notification and presses Escape
- **THEN** the modal closes and focus returns to the row that opened it

### Requirement: The notification panel distinguishes loading, empty, stale, and error states
The panel SHALL expose an accessible loading state while no successful response exists, an empty state only after a successful empty response, and an inline recoverable error state when loading or refreshing fails. A failed refresh after a successful response SHALL retain the last successful items, mark them stale, and provide Retry rather than clearing them or reporting that there are no notifications.

#### Scenario: Initial request fails
- **WHEN** the first notification request fails
- **THEN** the panel shows an announced error with a Retry action
- **AND** it does not show the empty state

#### Scenario: Refresh fails after success
- **WHEN** the panel has displayed a successful feed and a later refresh fails
- **THEN** the prior items remain visible with stale/retry context
- **AND** their read state is not reset

#### Scenario: Successful response is empty
- **WHEN** the server successfully returns no feed items
- **THEN** the panel shows the intentional no-notifications empty state

### Requirement: Notification refresh and realtime lifecycle remain reliable
The notification panel SHALL refresh on every opening, prevent overlapping or stale requests from overwriting newer results, poll at a bounded interval only while the document is visible, reconcile realtime supervisor messages by stable key, and clean up timers, requests, modal state, and Echo subscriptions when its Alpine instance is destroyed during soft navigation. HTTP/database notifications SHALL remain usable when Reverb is unavailable.

#### Scenario: Panel is reopened
- **WHEN** a user closes a previously loaded panel, new attendance or history activity occurs, and the panel is opened again
- **THEN** the panel requests fresh data and shows the new activity without a full page reload

#### Scenario: Soft navigation replaces the shell instance
- **WHEN** `#main-layout` is destroyed and initialized during soft navigation
- **THEN** the prior notification timers and Echo handler are removed
- **AND** the new Alpine instance receives subsequent notifications exactly once

#### Scenario: Reverb is unavailable
- **WHEN** the realtime connection cannot be established or disconnects
- **THEN** the panel continues to load and refresh database-backed notification data over HTTP
- **AND** the user is not shown internal transport errors

### Requirement: Notification feed performance is bounded
The notification implementation SHALL use bounded source queries, avoid N+1 label lookups, separate lightweight unread refreshes from full detail calculation, cache only recomputable aggregate data, and never issue overlapping panel requests. Read-state storage SHALL prune records older than 90 days without deleting authoritative activity or Laravel notification records.

#### Scenario: Feed contains many historical records
- **WHEN** more than the configured visible and 30-day window limits exist
- **THEN** source queries and the response remain bounded
- **AND** the response indicates additional items without loading unbounded records

#### Scenario: Old read states are pruned
- **WHEN** a derived notification read state is older than 90 days
- **THEN** scheduled pruning may remove only that read receipt
- **AND** no attendance, call/form, sales, or supervisor source record is deleted

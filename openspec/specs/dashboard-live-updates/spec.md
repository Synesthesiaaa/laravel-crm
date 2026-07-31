## Purpose

Keep dashboard data synchronized after form submissions and Data Master edits
without requiring a browser tab reload.

## Requirements

### Requirement: Successful dashboard data changes broadcast a campaign-scoped update signal

The application MUST broadcast a `dashboard.data.updated` event on the private
`dashboard.{campaign}` channel after a form submission or Data Master edit has
successfully committed. The payload MUST include the campaign code, form type,
record identifier, action, and timestamp, and MUST NOT include submitted field
values.

#### Scenario: A dynamic campaign form is submitted successfully

- **WHEN** a valid dynamic campaign form is committed
- **THEN** one dashboard update event is broadcast for that campaign with action
  `submitted`
- **AND** the event is dispatched only after the database transaction commits

#### Scenario: A Data Master record is edited successfully

- **WHEN** an authorized Data Master update completes successfully
- **THEN** one dashboard update event is broadcast for the record's campaign
  with action `updated`

#### Scenario: A write fails

- **WHEN** validation, authorization, or persistence fails
- **THEN** no dashboard update event is broadcast

### Requirement: Published dashboard layouts broadcast a campaign-scoped update

The application MUST broadcast a layout-update event on the private `dashboard.{campaign}` channel after an authorized dashboard layout is saved successfully. The event MUST include only the campaign code, action, and timestamp.

#### Scenario: An admin publishes a layout

- **WHEN** an authorized dashboard layout update commits successfully
- **THEN** one layout update event is broadcast for that campaign with action `layout_updated`

#### Scenario: Layout save fails

- **WHEN** validation, authorization, or persistence fails
- **THEN** no layout update event is broadcast

### Requirement: Dashboard viewers refresh without a browser reload

The dashboard MUST subscribe to the authenticated viewer's active campaign
channel and MUST refresh its current server-rendered data when a matching
data-update or layout-update event arrives. The refresh MUST use the existing
soft-navigation boundary, keep the current URL filters, and preserve the
persistent telephony shell.

#### Scenario: A matching campaign data event arrives

- **WHEN** the dashboard receives `dashboard.data.updated` for its campaign
- **THEN** it schedules one debounced soft refresh of the current dashboard URL
- **AND** the page updates without a user-initiated tab reload

#### Scenario: A matching campaign layout event arrives

- **WHEN** the dashboard receives `dashboard.layout.updated` for its campaign
- **THEN** it schedules one debounced soft refresh of the current dashboard URL
- **AND** the page updates without a user-initiated tab reload

#### Scenario: Events arrive in a burst

- **WHEN** multiple matching data or layout events arrive within the debounce window
- **THEN** the dashboard performs one refresh after the burst rather than one
  request per event

### Requirement: A disconnected WebSocket has a bounded fallback

The dashboard MUST perform a low-frequency refresh while Echo is unavailable or
disconnected, and MUST stop that fallback while the WebSocket is connected. The
fallback MUST be torn down during dashboard soft-navigation teardown.

#### Scenario: Reverb is unavailable

- **WHEN** the dashboard cannot establish or maintain its Echo connection
- **THEN** it refreshes at the configured fallback interval without a browser
  reload

#### Scenario: Echo reconnects

- **WHEN** Echo reports a connected WebSocket
- **THEN** fallback refreshes are not scheduled until the connection is lost

### Requirement: Dashboard update channels are authorized and narrowly scoped

The private dashboard channel MUST require an authenticated user with access to
the requested active campaign. Events for other campaigns MUST NOT be delivered
to that viewer, and telephony or disposition events MUST NOT trigger this
dashboard refresh path.

#### Scenario: An authenticated user subscribes to an accessible campaign

- **WHEN** the user requests authorization for an active campaign they can
  access
- **THEN** the private channel authorization succeeds

#### Scenario: A user requests an inaccessible campaign

- **WHEN** the user requests authorization for a campaign outside their access
- **THEN** the private channel authorization fails

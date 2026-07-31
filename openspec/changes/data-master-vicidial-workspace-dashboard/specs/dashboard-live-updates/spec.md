## ADDED Requirements

### Requirement: Published dashboard layouts broadcast a campaign-scoped update

The application MUST broadcast a layout-update event on the private `dashboard.{campaign}` channel after an authorized dashboard layout is saved successfully. The event MUST include only the campaign code, action, and timestamp.

#### Scenario: An admin publishes a layout

- **WHEN** an authorized dashboard layout update commits successfully
- **THEN** one layout update event is broadcast for that campaign with action `layout_updated`

#### Scenario: Layout save fails

- **WHEN** validation, authorization, or persistence fails
- **THEN** no layout update event is broadcast

## MODIFIED Requirements

### Requirement: Dashboard viewers refresh without a browser reload

The dashboard MUST subscribe to the authenticated viewer's active campaign channel and MUST refresh its current server-rendered data when a matching data-update or layout-update event arrives. The refresh MUST use the existing soft-navigation boundary, keep the current URL filters, and preserve the persistent telephony shell.

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
- **THEN** the dashboard performs one refresh after the burst rather than one request per event

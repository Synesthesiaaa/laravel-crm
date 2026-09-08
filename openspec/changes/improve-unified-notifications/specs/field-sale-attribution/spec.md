## ADDED Requirements

### Requirement: Notification daily performance uses the dashboard sales result
Each daily performance notification SHALL consume the same active-campaign sales result used by the dashboard for the item's application-timezone date and its 06:00 inclusive to 18:00 exclusive business range. The current date may remain live; historical dates SHALL be calculated against their own date-specific range. It SHALL use the same sales mode, qualifying submissions, counts, amounts, per-form totals, Top Agent result, leaderboard ordering, and dashboard amount-visibility settings, and SHALL NOT implement an independent sales query, attribution fallback, or ranking rule.

#### Scenario: Dashboard and notification are viewed for the same campaign and day
- **WHEN** an authenticated user opens the dashboard and the performance notification for the same active campaign and date
- **THEN** team sales count, permitted team sales amount, Top Agent, permitted Top Agent amount, per-form totals, and leaderboard ordering match

#### Scenario: Current user has individual sales
- **WHEN** a dashboard leaderboard row matches any supported alias of the authenticated user
- **THEN** the notification shows that row's sales count and permitted sales amount as the user's individual performance
- **AND** the same sale is not counted again through another alias

#### Scenario: Dashboard amount display is disabled
- **WHEN** the active campaign's dashboard layout disables a monetary display
- **THEN** the corresponding notification preview and modal amount are omitted
- **AND** the amount is not sent as display data for CSS-only concealment

#### Scenario: No qualifying sales exist
- **WHEN** the shared dashboard result contains zero qualifying sales for the daily range
- **THEN** the notification shows zero team and individual counts, no Top Agent, and an explicit no-sales-yet state
- **AND** it does not derive sales from call dispositions or lead history

#### Scenario: Notification monthly comparison matches the dashboard
- **WHEN** performance details are opened for an active campaign and available date
- **THEN** current and equivalent previous-month sales counts, amounts, differences, and percentages use the dashboard's existing monthly summary result
- **AND** monetary comparison fields are omitted when the dashboard hides monetary totals

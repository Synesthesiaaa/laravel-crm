## ADDED Requirements

### Requirement: The shared notification center is responsive and lifecycle-safe
The shared CRM shell SHALL render the notification control without horizontal viewport overflow, SHALL keep the bell and panel controls at least 44 by 44 CSS pixels where practical, SHALL present a desktop popover and a small-screen viewport-safe panel, and SHALL preserve notification state and operability across supported 375px, 768px, 1024px, and 1440px viewport widths. The shell SHALL destroy the previous notification component cleanly before soft-navigation replacement.

#### Scenario: Notification panel opens at 375px
- **WHEN** an authenticated user opens notifications at a 375px viewport width
- **THEN** all notification copy and controls remain within the viewport without horizontal scrolling
- **AND** the details modal has a visible close control and unobscured focused elements

#### Scenario: Notification panel opens on desktop
- **WHEN** an authenticated user opens notifications at a 1440px viewport width
- **THEN** the panel is aligned to the bell, uses the existing shell design tokens, and does not obscure unrelated persistent navigation unnecessarily

#### Scenario: Reduced motion is requested
- **WHEN** the user has enabled reduced motion
- **THEN** notification panel and modal transitions are removed or reduced without changing functionality

#### Scenario: Shell is replaced by soft navigation
- **WHEN** the current `#main-layout` is replaced
- **THEN** notification timers, requests, focus state, and realtime subscriptions from the old shell instance are cleaned up before the new instance initializes

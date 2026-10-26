## ADDED Requirements

### Requirement: Alpine component initialization and teardown are singular

An Alpine component SHALL run its `init()` method once per initialized component tree and SHALL release page-scoped timers, listeners, subscriptions, and polling when the component is destroyed during soft navigation.

#### Scenario: Component initializes on a page load
- **WHEN** Alpine initializes a component that defines `init()`
- **THEN** its setup runs once without an additional explicit `x-init="init()"` invocation

#### Scenario: Component initializes after soft navigation
- **WHEN** soft navigation replaces a page-scoped component tree
- **THEN** the old component releases its resources before the replacement initializes once

#### Scenario: Shared widget remains mounted
- **WHEN** soft navigation replaces only the main page content
- **THEN** global telephony widgets outside the swap boundary remain mounted and do not initialize again

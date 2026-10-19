## Context

The user explicitly removed the illustrative workspace from the approved landing direction. Existing Taste guidance and shared design tokens still apply.

## Goals / Non-Goals

Remove the illustration and its styles, preserve the content and sign-in flow, and adapt the hero to a heading/description composition. Do not add a replacement mockup or change authenticated screens.

## Decisions

Use the existing headline on the left and existing description/actions on the right on desktop, stacking at narrow widths. Keep theme, branding, remaining sections, and accessibility behavior. Update the existing test to assert that illustration markup and fictional data are absent.

## Risks / Trade-offs

Removing one grid child without rebalancing would leave an empty column. Move the existing introductory text and actions into the second column, and remove illustration-only CSS including breakpoint overrides.

## Validation

Run focused landing tests, Pint, build, and Playwright at representative widths in both themes. Sync the public landing spec and archive after validation. Continue using the isolated SQLite preview because configured MySQL is offline.

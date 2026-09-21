## Context

The CRM login surface is a repeated operational threshold. Its essential job is to establish the configured organization identity, collect credentials, optionally establish campaign context, and submit the login request. The current surface has a polished signal-desk split composition, but its context rail repeats information that the form heading and campaign field already communicate.

## Goals / Non-Goals

**Goals:**

- Make the credential task the only dominant action and reduce visual scanning time.
- Keep the configured brand visible without retaining a competing context rail.
- Preserve the optional campaign selector as a clearly grouped, native control.
- Retain dark/light themes, visible focus, validation/status feedback, reduced motion, touch sizing, and responsive overflow safety.
- Keep the existing operational visual language: DM Sans, charcoal/light surfaces, Signal Magenta action signal, and the auth sheet silhouette.

**Non-Goals:**

- No changes to authentication logic, routes, request fields, validation, CSRF, campaign data, session handling, or authorization.
- No new authentication feature, dependency, asset, marketing claim, or backend behavior.
- No redesign of the authenticated CRM shell or other public pages.

## Decisions

### Use a linear entry composition

The page will use one centered column. The configured brand lockup sits above the single auth sheet, followed by the form heading, feedback, credentials, optional campaign group, and one submit action. This removes the desktop sidebar and avoids making users parse two separate content regions before acting.

Alternative considered: retain the split rail and only hide its copy at smaller widths. Rejected because the rail's purpose is the duplicated context; retaining it would preserve layout complexity without adding a necessary task.

### Keep one useful card and remove decorative repetition

The auth sheet remains because it groups the credential task and feedback state. The context headline, descriptive paragraph, scope badge, second atmospheric wash, and submit icon are removed because they do not add a new decision or improve form completion. The existing theme toggle remains available as a user preference control.

### Preserve semantic and state contracts

The named main landmark, connected heading, visible labels, native campaign select, status/error roles, data hooks, autocomplete values, and all request fields remain intact. Simplification changes composition and copy density, not the login contract.

### Keep interaction feedback explicit

The token-derived contrast-safe action colors, visible keyboard focus rings, error treatment, selection/caret styling, and reduced-motion rules remain. Distillation removes decoration, not feedback needed to operate the form confidently.

## Risks / Trade-offs

- [Risk] Removing the context copy could make the page feel less branded. → Keep the configured brand lockup prominent above the auth sheet and retain the product's signal color and auth silhouette.
- [Risk] A single column may feel too narrow on wide screens. → Center a readable, bounded form column and use spacing rather than a second content rail as the visual structure.
- [Risk] Shorter copy could hide campaign meaning. → Keep the campaign selector visibly grouped as workspace context whenever campaigns are available.

## Migration Plan

No migration is required. Deploy the Blade and CSS changes with the normal Vite build. Rollback is a file-level revert; no persisted data or authentication state changes.

## Open Questions

None for this implementation pass.

---
name: Laravel CRM
description: A focused telephony-first operations console for campaign-scoped call-center work.
colors:
  primary: "#e91e8c"
  primary-hover: "#f43f9e"
  primary-muted: "rgba(233, 30, 140, 0.15)"
  primary-foreground: "#ffffff"
  success: "#22c55e"
  success-muted: "rgba(34, 197, 94, 0.15)"
  success-fg: "#86efac"
  warning: "#f59e0b"
  warning-muted: "rgba(245, 158, 11, 0.15)"
  warning-fg: "#fde68a"
  danger: "#ef4444"
  danger-hover: "#f87171"
  danger-muted: "rgba(239, 68, 68, 0.15)"
  danger-fg: "#fca5a5"
  info: "#3b82f6"
  info-muted: "rgba(59, 130, 246, 0.15)"
  info-fg: "#93c5fd"
  surface: "#0a0a0a"
  surface-1: "#111111"
  surface-2: "#1a1a1a"
  surface-3: "#242424"
  surface-card: "#141414"
  surface-elevated: "#1f1f1f"
  on-surface: "#fafafa"
  on-surface-muted: "#a1a1aa"
  on-surface-dim: "#71717a"
  border: "rgba(255,255,255,0.08)"
  border-strong: "rgba(255,255,255,0.14)"
typography:
  display:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.75rem"
    fontWeight: 700
    lineHeight: 1
  headline:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 700
    lineHeight: 1.4
  title:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
  body:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.5
  field:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.5
  action:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.5
  label:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.05em"
  status:
    fontFamily: "DM Sans, Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.7rem"
    fontWeight: 600
    lineHeight: 1.2
    letterSpacing: "0.03em"
rounded:
  xs: "4px"
  sm: "8px"
  control: "0.625rem"
  card: "0.75rem"
  hero: "16px"
  auth: "26px"
  pill: "9999px"
spacing:
  space-1: "0.25rem"
  space-2: "0.5rem"
  space-3: "0.75rem"
  space-4: "1rem"
  space-5: "1.25rem"
  space-6: "1.5rem"
  space-8: "2rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.primary-foreground}"
    typography: "{typography.action}"
    rounded: "{rounded.sm}"
    padding: "0.5625rem 1.125rem"
    height: "2.75rem"
  button-secondary:
    backgroundColor: "{colors.surface-2}"
    textColor: "{colors.on-surface}"
    typography: "{typography.action}"
    rounded: "{rounded.sm}"
    padding: "0.5rem 1rem"
    height: "2.75rem"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.on-surface-muted}"
    typography: "{typography.action}"
    rounded: "{rounded.sm}"
    padding: "0.5rem 1rem"
    height: "2.75rem"
  input-field:
    backgroundColor: "{colors.surface-2}"
    textColor: "{colors.on-surface}"
    typography: "{typography.field}"
    rounded: "{rounded.sm}"
    padding: "0.5625rem 0.875rem"
    height: "2.75rem"
  card:
    backgroundColor: "{colors.surface-card}"
    rounded: "{rounded.card}"
    padding: "1.25rem 1.5rem"
  badge-primary:
    backgroundColor: "{colors.primary-muted}"
    textColor: "{colors.primary}"
    typography: "{typography.status}"
    rounded: "{rounded.pill}"
    padding: "0.1875rem 0.5rem"
  navigation:
    backgroundColor: "{colors.surface-1}"
    textColor: "{colors.on-surface-muted}"
    rounded: "{rounded.sm}"
    width: "17.5rem"
  notification-dropdown:
    backgroundColor: "{colors.surface-2}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.card}"
    width: "20rem"
  widget-launcher:
    backgroundColor: "{colors.surface-elevated}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.pill}"
    size: "3rem"
    height: "3rem"
    width: "3rem"
  auth-surface:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.auth}"
    padding: "2.5rem"
    width: "25rem"
---

# Design System: Laravel CRM

## Overview

**Creative North Star: "The Operational Signal Desk"**

Laravel CRM is an instrument panel for agents, Team Leaders, and administrators who need to see the next actionable state without leaving the call workflow. The visual system is dark-first and telephony-aware: a quiet charcoal surface stack gives the magenta brand signal and semantic status colors room to communicate clearly during long operational sessions.

The density is compact but not cramped. Fixed navigation, a sticky header, short labels, compact cards and tables, and persistent floating call/form controls keep the workspace scannable while preserving room for lead data and form completion. Surfaces are calm at rest and gain lift only when they become interactive or layered. The public login is intentionally more expressive, using a glass sheet as an entry moment without turning the operational workspace into a decorative canvas.

**Key Characteristics:**

- Dark-first, dual-theme operations shell.
- Magenta as a scarce action, focus, and active-state signal.
- Semantic status colors for connection, outcome, attention, and error.
- Compact, data-rich surfaces designed for repeated scanning.
- Telephony widgets that remain available without obscuring the primary work.
- Visible focus, generous touch targets, contained overflow, and reduced-motion support.

## Colors

The palette is a near-black instrument panel with a single vivid brand signal and restrained semantic accents. The frontmatter is the normative dark-theme token set. The light theme rebinds the same semantic variables to paper-white surfaces, black ink, darker status foregrounds, and low-opacity dark borders; components should not invent a separate light palette.

### Primary

- **Signal Magenta** (`primary`): Reserve for primary actions, active navigation, focus rings, selected states, KPI emphasis, and the edge of floating telephony controls.
- **Signal Magenta Hover** (`primary-hover`): A brighter response state for primary controls; it should not become a second decorative accent.
- **Signal Wash** (`primary-muted`): A translucent selected/active surface behind navigation items, badges, comparison rows, and unread notifications.
- **Signal Ink** (`primary-foreground`): The readable foreground for magenta actions.

### Status

- **Connection Green** (`success` and its muted/foreground variants): Ready, connected, successful, or available states.
- **Queue Amber** (`warning` and its muted/foreground variants): Pending work, attention, reconnecting, or paused states.
- **Alert Red** (`danger`, `danger-hover`, and its muted/foreground variants): Destructive actions, failed calls, wrap-up attention, and validation errors.
- **Information Blue** (`info` and its muted/foreground variants): Informational states and neutral operational context.

Status colors are not decoration. Pair them with a label, icon, or shape so that meaning never depends on hue alone.

**The One Signal Rule.** Use the primary accent to answer “what is active or actionable?” Keep it rare enough that a magenta edge, label, or control is immediately legible.

### Neutral

- **Carbon Base** (`surface`): The page canvas and deepest shell background.
- **Charcoal Layers** (`surface-1`, `surface-2`, `surface-3`): Sidebar/header, field and control surfaces, and hover or nested layers.
- **Card Charcoal** (`surface-card`): Resting content surfaces such as cards, tables, and charts.
- **Elevated Charcoal** (`surface-elevated`): Floating controls, widget headers, and surfaces that need to sit above cards.
- **Primary Ink** (`on-surface`): Headings, values, and essential content.
- **Muted Ink** (`on-surface-muted`): Supporting copy, navigation labels, and secondary data.
- **Dim Ink** (`on-surface-dim`): Metadata, placeholders, timestamps, and low-priority labels.
- **Hairline** (`border`): Quiet structural separation.
- **Strong Hairline** (`border-strong`): Interactive boundaries, overlay edges, and stronger table or control separation.

**The Tonal Layer Rule.** Prefer a change in surface tone before adding a border or shadow. Every layer should explain what is above or below it.

## Typography

**Display Font:** DM Sans (with Instrument Sans, system UI, and system sans-serif fallbacks)

**Body Font:** DM Sans (with Instrument Sans, system UI, and system sans-serif fallbacks)

**Label/Mono Font:** UI monospace is used only for keyboard hints, technical identifiers, and raw API-style values.

**Character:** DM Sans is compact, highly legible, and operational rather than ornamental. Weight and case do the hierarchy work: strong headings and values, quiet supporting copy, and small uppercase labels for metadata and status.

### Hierarchy

- **Display** (`display`): KPI values and the strongest auth/workspace titles; use the compact line box for numbers and short labels.
- **Headline** (`headline`): Page titles, welcome statements, and major section introductions.
- **Title** (`title`): Card headings, modal titles, and component-level labels that need clear but quiet authority.
- **Body** (`body`): Explanatory copy and default page text; keep dense descriptions short and break long operational detail into fields or rows.
- **Field** (`field`): User-entered values and select contents; preserve the larger readable control size rather than shrinking inputs to fit.
- **Action** (`action`): Buttons and explicit controls; use semibold weight and a clear verb.
- **Label / Status** (`label`, `status`): Uppercase, tracked metadata and compact state chips. Never use this treatment for paragraphs.

Use tabular numerals for counts, currency, durations, and report columns. Icons should clarify the action or state, not replace its accessible text.

**The Case Is a Signal Rule.** Uppercase and letter spacing belong to labels, navigation groups, table headers, and status metadata; headings and body copy remain in sentence case.

## Layout

The application uses a fixed operational shell on desktop and a full-width, off-canvas shell on smaller screens. The sidebar is 280px wide when expanded and 72px when collapsed; the sticky header is 64px tall. At widths below 1024px the sidebar leaves the canvas and opens over a dimmed backdrop, while the main content keeps its width and avoids horizontal page overflow.

Content gutters step from 16px on narrow screens to 24px at 640px, 32px at 1024px, and 40px at 1440px. Page content uses a compact top inset and a larger bottom runway for scrolling. Keep page headers, filters, cards, and tables aligned to the same gutter rather than introducing local columns without a clear task reason.

Use responsive grids instead of fixed desktop widths: form fields auto-fit from roughly 13rem, filter fields from roughly 12rem, and dashboard/stat layouts grow from one or two columns into wider groups. The Agent Screen becomes a lead-and-form column beside a 288–320px call-control rail at the large breakpoint. Wide reports preserve readable column widths through contained scrolling and, where needed, a sticky totals column rather than allowing the entire page to overflow.

The floating call and quick-form controls are a persistent bottom-right stack with a 24px base inset, 48px launchers, and a 56px separation. At the 1024px breakpoint they can become a two-panel split workspace with 16px outer margins and gap; on smaller screens they return to corner launchers and anchored panels. The floating controls must remain reachable without covering the current lead or primary form action.

## Elevation & Depth

This is a layered system with purposeful lift. Tonal surfaces do most of the structural work; shadows identify cards, overlays, and floating tools. At rest, cards use a low ambient shadow, interactive cards may lift slightly on hover, and modals, dropdowns, slide-overs, and telephony panels use the highest shared elevation. The light theme reduces shadow opacity while preserving the same hierarchy.

### Shadow Vocabulary

- **Ambient low** (`shadow-1`: `0 1px 3px rgba(0,0,0,.4)` in dark mode): Resting cards, charts, and table shells.
- **Interactive lift** (`shadow-2`: `0 4px 6px -1px rgba(0,0,0,.4), 0 2px 4px -2px rgba(0,0,0,.3)` in dark mode): Hovered cards, inline edit panels, and secondary floating layers.
- **Overlay lift** (`shadow-3`: `0 10px 15px -3px rgba(0,0,0,.4), 0 4px 6px -4px rgba(0,0,0,.3)` in dark mode): Menus, notifications, modals, slide-overs, toasts, and expanded widgets.
- **Signal glow** (`shadow-glow`: `0 0 20px rgba(233,30,140,.25)` in dark mode): Hover feedback for primary actions; use sparingly and never as the default card shadow.

The login sheet is the deliberate exception: its translucent surface, backdrop blur, inset highlights, and broad shadow create a sense of entry. Keep that treatment scoped to authentication and similarly intentional public-entry surfaces.

**The Layer-First Rule.** Add tonal separation before adding shadow. Shadows should describe a surface crossing a boundary, not decorate every component.

## Shapes

The form language is gently rounded and functional. Standard buttons use compact 8px corners, controls and interactive tabs use the 10px control radius, cards and tables use 12px, hero surfaces use 16px, and status badges/avatars use a full pill. The login sheet intentionally uses a larger 26px silhouette; it is a signature auth shape, not a default for the app shell.

Borders are one-pixel, low-contrast hairlines by default and become stronger only for focus, control boundaries, table structure, or overlay edges. Inputs use a tonal fill, not a white field, and focus is communicated by a magenta border plus a soft ring. Destructive/error states use the corresponding semantic ring. Keep clipping and overflow inside the component so long labels, tables, and notification details cannot widen the page.

All interactive controls should retain at least a 44px touch target, including compact icon buttons and dismiss controls. Rounded geometry must support the content: do not add a pill to a large text container merely to make it feel friendly.

## Components

### Buttons

Buttons are compact, verb-led controls with a clear primary/secondary hierarchy.

- **Shape:** 8px corners, semibold action typography, inline-flex alignment, and a minimum 44px interaction height.
- **Primary:** Signal Magenta fill with light foreground; use for the main save, connect, submit, or confirm action on a surface.
- **Hover / Focus:** Brighten the primary fill and allow a restrained signal glow; every button gets a visible focus ring with a 3px offset.
- **Secondary:** Charcoal-2 fill with a strong hairline and primary ink; use for adjacent alternatives and non-destructive secondary actions.
- **Ghost / Link:** Transparent or minimally bordered, muted ink at rest, and a tonal hover surface; use for dismiss, retry, navigation, and low-emphasis actions.
- **Danger:** Alert Red is reserved for destructive or hang-up actions and keeps the same sizing and focus behavior as primary actions.

### Chips and Status Badges

Chips are compact state annotations, not miniature buttons. Use a pill shape, a short label, and a dot or icon when the state matters at a glance. Connection states, call states, notification types, and data statuses use the semantic palette consistently. A chip may pulse only for a live call state; static status should remain still.

### Cards and Containers

Cards provide quiet grouping for dashboards, forms, charts, activity, and telephony tools. Use Card Charcoal with a one-pixel hairline and the ambient low shadow. Internal padding comes from the shared spacing rhythm, commonly one to one-and-a-half spacing units for compact tools and larger padding for hero or empty states. Hover lift is reserved for cards that are actually actionable; static cards must not jump.

### Inputs and Fields

Fields stack an uppercase label, a tonal control surface, and optional help/error copy. Standard inputs/selects/textareas share the same fill, border, radius, readable field typography, and 44px minimum target. Focus uses the primary border and a soft primary ring; errors replace both with the danger treatment. Placeholder and helper text use dim ink and must remain readable in both themes.

The login surface uses a separate floating-label field pattern with a leading icon and a 12px field radius. Preserve its label transition, visible focus, and autocomplete semantics when extending authentication.

### Navigation

The sidebar is a fixed, scrollable navigation rail with a 64px brand header, 10px item corners, 44px minimum item targets, and compact uppercase section labels. Resting items use muted ink; hover uses the next surface tone; the active route uses the signal wash, signal text, and an inset magenta edge. In collapsed mode, labels and group contents disappear while icons remain centered and the active route receives an inset outline. Mobile navigation is an off-canvas panel with a dismissible backdrop, not a permanently compressed rail.

### Overlays and Notifications

Dropdowns, notification trays, modals, slide-overs, and toasts share the elevated surface and overlay shadow. Notification items remain large enough to scan and activate, unread items use the signal wash, and loading, empty, stale, and retry states are explicit. Modal content should scroll inside the dialog or panel; never force the page behind it to horizontally scroll.

### Signature Telephony Workspace

The softphone and quick-form controls are first-class tools, not decorative floating action buttons. Launchers sit in a persistent corner stack, expose connection state through a dot and accessible label, and open into a resizable, bounded panel. The phone panel keeps VICIdial session state visible, preserves the iframe session while minimized, offers split view at desktop widths, and uses the same field, button, chip, and overlay language as the rest of the CRM. The quick-form panel follows the same shell but lets the agent select or open a campaign form without leaving the current lead workflow.

### Login Glass Sheet

Authentication is the system’s expressive threshold: a centered, max-width sheet over a soft radial/linear atmosphere, with translucent layering, broad lift, a 26px radius, floating labels, and a full-width magenta submit action. It supports both themes and reduced motion. Keep the atmosphere quiet enough that credentials, campaign selection, and error feedback remain the visual priority.

## Do's and Don'ts

### Do:

- **Do** use the frontmatter tokens and existing CSS custom properties as the source of truth.
- **Do** keep magenta for action, selection, focus, active navigation, and meaningful emphasis.
- **Do** pair semantic colors with text, icons, or structure so state is never color-only.
- **Do** preserve the 44px interaction target and visible focus treatment on every interactive control.
- **Do** keep telephony state, campaign scope, and the next call action visible in the working context.
- **Do** contain table and notification overflow inside their own scroll regions.
- **Do** honor the dark/light theme switch and `prefers-reduced-motion: reduce` behavior.
- **Do** use lift and motion to clarify state changes, not to make static data feel busy.

### Don't:

- **Don't** turn the operational shell into a glassmorphism or gradient-heavy composition; reserve that atmosphere for the login threshold and explicitly branded entry surfaces.
- **Don't** add a second brand accent or use semantic colors as decoration.
- **Don't** nest arbitrary rounded cards inside rounded cards without a real grouping need.
- **Don't** make static cards translate on hover or hide important actions behind hover-only behavior.
- **Don't** rely on color alone for call, connection, validation, or notification state.
- **Don't** shrink labels, fields, or icon controls below the established touch and readability floor to fit a dense layout.
- **Don't** allow a wide report, widget, or notification detail to create page-level horizontal overflow.
- **Don't** add motion that continues, pulses, or shimmers when the user has requested reduced motion.

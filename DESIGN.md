---
name: Laravel CRM
description: A framed working sheet for campaign-scoped call-center operations.
colors:
  primary: "#e91e8c"
  primary-hover: "#f43f9e"
  primary-foreground: "#0a0a0a"
  action: "#984064"
  primary-muted: "#f5eaf0"
  workspace-control: "#292927"
  workspace-control-hover: "#41413e"
  workspace-control-text: "#ffffff"
  surface: "#fbfbfa"
  surface-1: "#f5f5f3"
  surface-2: "#f7f7f5"
  surface-3: "#ededea"
  surface-card: "#ffffff"
  surface-elevated: "#ffffff"
  on-surface: "#242422"
  on-surface-muted: "#60605b"
  on-surface-dim: "#6c6c66"
  border: "#e7e7e2"
  border-strong: "#cdcdc6"
  success: "#34714b"
  success-muted: "rgba(34, 197, 94, 0.15)"
  success-fg: "#166534"
  warning: "#926519"
  warning-muted: "rgba(245, 158, 11, 0.15)"
  warning-fg: "#92400e"
  danger: "#b13d3d"
  danger-muted: "rgba(239, 68, 68, 0.15)"
  danger-fg: "#991b1b"
  info: "#386c8d"
  info-muted: "rgba(59, 130, 246, 0.15)"
  info-fg: "#1e40af"
typography:
  headline:
    fontFamily: '"Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif'
    fontSize: "24px"
    fontWeight: 600
    lineHeight: 1.3
    letterSpacing: "-0.025em"
  title:
    fontFamily: '"Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif'
    fontSize: "18px"
    fontWeight: 600
    letterSpacing: "-0.025em"
  body:
    fontFamily: '"Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif'
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  field:
    fontFamily: '"Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif'
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
  action:
    fontFamily: '"Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif'
    fontSize: "14px"
    fontWeight: 500
    lineHeight: 1.5
  label:
    fontFamily: '"Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif'
    fontSize: "14px"
    fontWeight: 500
    letterSpacing: "0"
rounded:
  hint: "4px"
  control: "7px"
  search: "8px"
  panel: "10px"
  auth: "12px"
  pill: "999px"
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
    backgroundColor: "{colors.workspace-control}"
    textColor: "{colors.workspace-control-text}"
    typography: "{typography.action}"
    rounded: "{rounded.control}"
    padding: "0.5625rem 1.125rem"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.workspace-control-hover}"
  button-secondary:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.on-surface}"
    typography: "{typography.action}"
    rounded: "{rounded.control}"
    padding: "0.5rem 1rem"
    height: "44px"
  button-ghost:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.on-surface-muted}"
    typography: "{typography.action}"
    rounded: "{rounded.control}"
    padding: "0.5rem 1rem"
    height: "44px"
  input-field:
    backgroundColor: "{colors.surface-card}"
    textColor: "{colors.on-surface}"
    typography: "{typography.field}"
    rounded: "{rounded.control}"
    padding: "0.5625rem 0.875rem"
    height: "44px"
  card:
    backgroundColor: "{colors.surface-card}"
    rounded: "{rounded.panel}"
  badge-primary:
    backgroundColor: "{colors.primary-muted}"
    textColor: "{colors.action}"
    rounded: "{rounded.pill}"
    padding: "0.1875rem 0.5rem"
  navigation:
    backgroundColor: "{colors.surface-1}"
    textColor: "{colors.on-surface-muted}"
    rounded: "{rounded.control}"
    width: "248px"
  workspace-tab:
    textColor: "{colors.on-surface-muted}"
    typography: "{typography.action}"
    padding: "8px 0"
    height: "48px"
  widget-launcher:
    backgroundColor: "{colors.surface-elevated}"
    textColor: "{colors.on-surface}"
    rounded: "{rounded.pill}"
    size: "48px"
  auth-surface:
    backgroundColor: "{colors.surface-card}"
    rounded: "{rounded.auth}"
    padding: "32px"
---

# Design System: Laravel CRM

## Overview

**Creative North Star: "The Operational Signal Desk"**

The authenticated CRM now expresses the incumbent Operational Signal Desk as a **Framed sheet**: labelled neutral navigation and a quiet utility header frame a flat working surface. Campaign context, records, operational status and the next useful action carry the hierarchy. The neutral primary buttons and restrained magenta selection/focus signal support long sessions without turning each screen into a dashboard of decorative cards.

This is a code-led Operate extension of the established dual-theme system. The default shell opens in light mode and restores an explicitly saved dark preference. Preserve the configured organization name, logo, favicon and accessible alternatives; Laravel CRM remains the default product name. This document describes the authenticated shell and matching sign-in materials. The existing public landing composition remains a separate scoped expression in `resources/css/landing.css`; do not flatten or restyle it through workspace rules.

**Key Characteristics:**

- Flat working sheets framed by role-appropriate navigation.
- Dual-theme neutrals with restrained magenta focus and selection.
- Sentence-case labels, readable platform sans and tabular operational figures.
- Records before setup, with native creation disclosures.
- Persistent campaign and call/form context with safe scrolling space.
- Visible keyboard focus, contained overflow and reduced-motion support.

## Colors

The frontmatter records the actual light-mode workspace bindings. `resources/css/app.css` owns shared primitives; `resources/css/crm-minimal.css` supplies the more specific application and sign-in bindings. Use the live semantic properties rather than copying a mode's literal colors into a new component.

### Primary

- **Signal Magenta** (`primary`, `primary-hover`, `primary-foreground`): Preserved brand identity and shared brand primitives. Standard workspace primary buttons use neutral control tokens.
- **Quiet Magenta** (`action`): Active navigation icons, selected tab rules, keyboard focus, text accents and carets.
- **Signal Wash** (`primary-muted`): Selected or unread context without filling a large surface with the brand accent.
- **Control Ink** (`workspace-control`, `workspace-control-hover`, `workspace-control-text`): Strong neutral action hierarchy that reverses appropriately between modes.

**The One Signal Rule.** Use magenta to identify selection, focus and meaningful operational emphasis; give ordinary actions a neutral hierarchy.

### Neutral

- **Working Paper** (`surface`, `surface-card`): Quiet canvas and the continuous working sheet.
- **Frame Neutrals** (`surface-1`, `surface-2`, `surface-3`, `surface-elevated`): Navigation, hover regions, nested operational surfaces and overlays.
- **Working Ink** (`on-surface`, `on-surface-muted`, `on-surface-dim`): Essential data, supporting text and secondary context.
- **Hairlines** (`border`, `border-strong`): Group boundaries and readable control edges.

The dark mode rebinds these same properties:

| Token | Dark binding |
| --- | --- |
| surface | `#191a19` |
| surface-1 / surface-card | `#1e1f1e` |
| surface-2 / surface-elevated | `#242524` |
| surface-3 | `#2b2c2b` |
| on-surface | `#eeeee9` |
| on-surface-muted | `#b5b5ad` |
| on-surface-dim | `#a5a59c` |
| border | `#343631` |
| border-strong | `#4c4e47` |
| action | `#edacc7` |
| primary-muted | `#3b2a32` |
| workspace-control | `#ededeb` |
| workspace-control-hover | `#d9d9d6` |
| workspace-control-text | `#252523` |

Semantic status families retain their incumbent meanings: Connection Green for success/availability, Queue Amber for attention/pending work, Alert Red for error/destruction, and Information Blue for context. Pair color with text, icon or structure. Dark status bases use the shared app tokens (`#22c55e`, `#f59e0b`, `#ef4444`, `#3b82f6`), with foregrounds `#86efac`, `#fde68a`, `#fca5a5`, `#93c5fd`; translucent status washes remain shared.

## Typography

**Body and interface font:** Segoe UI, with Apple platform sans, Helvetica Neue and sans-serif fallbacks. The interface uses a platform stack for dependable controls and data; this is not a display-font recommendation for public brand surfaces.

**Label/Mono Font:** UI monospace appears only in technical identifiers and keyboard hints.

### Hierarchy

- **Headline:** Page titles use the frontmatter headline role. The dashboard title steps to 28px with the same semibold character.
- **Title:** Section headings use the title role; form-section titles use 16px semibold.
- **Body / Field:** Tables, fields and ordinary interface text use the readable body/field roles. Supporting page descriptions use 13px with 1.6 line-height and a maximum 65ch measure.
- **Action / Label:** Medium-weight sentence-case labels establish hierarchy without decorative uppercase tracking.
- **Operational figures:** Use tabular numerals. Lead dashboard values use 36px and supporting values scale from 18px to 24px; these are data treatments, not a display face.

**The Sentence Case Rule.** Use plain task labels and descriptive headings. Do not turn leftover tiny metadata, kickers or tracked labels into a reusable typography role.

## Layout

A fixed sidebar (248px expanded, 72px collapsed) and utility header (64px) frame the content. At widths below 1024px the sidebar becomes an off-canvas panel with a dismissible backdrop and keyboard dismissal. Preserve actual role and feature gates when extending navigation.

The working sheet has a maximum width of 1600px and desktop padding of 24px top, 32px sides and 160px bottom. Side padding becomes 24px below 1280px and 16px below 640px. The bottom runway keeps final rows and actions scrollable above persistent phone and form launchers; it is intentional, not spare space to remove.

Use aligned edges and `minmax(0, 1fr)` tracks. Directory groups use two columns with 32px gaps, becoming one column below 768px. The Agent Screen uses a flexible capture column and a 304px call rail with a 24px gap; at 1100px and below, controls move above capture fields and stop being sticky. Tables retain their columns inside labelled, focusable horizontal scroll regions rather than widening the page.

Default corner launchers are 48px with 8px clear separation, a 24px right inset, and the phone anchored 24px above the bottom. Persisted custom widget positions and split layouts remain user state. At desktop widths the existing split workspace can arrange panels with 16px outer margins/gap; preserve the live session roots when navigating or minimizing.

**The Shared Edge Rule.** Align headers, filters, rows and panels to the working-sheet gutter, and contain each wide region within that sheet.

## Elevation & Depth

The workspace and sign-in are flat at rest. Resting cards, tables, charts and mastheads use borders, whitespace and tonal changes; no glow or default card shadow. Navigation's active state uses a fine inset boundary. The login panel is now opaque and flat rather than the previous glass threshold.

### Shadow Vocabulary

- **Tool lift:** `0 4px 16px rgb(20 20 20 / 4%)` for persistent launchers and secondary floating layers.
- **Overlay lift:** `0 12px 40px rgb(20 20 20 / 10%)` for dropdowns and layered tools.

These values are the scoped workspace bindings of the shared shadow tokens. Keep modal and menu depth subordinate to readable content. Standard state transitions remain brief (150ms fast, 220ms standard tokens); the shell suppresses decorative reveal/stagger animations and removes motion under reduced-motion preference.

**The Flat Working Sheet Rule.** Organize static work with edges and spacing; use lift only to explain a floating or overlapping tool.

## Shapes

Panels, tables and report containers use restrained corners (10px). Standard actions, inputs, pagination and sidebar items use tighter control corners (7px). Search and brand marks use an 8px exception; the sign-in panel uses 12px. Badges, avatars and circular launchers retain their compact pill/circle silhouettes.

Use one-pixel boundaries and preserve at least 44px interaction targets. Keyboard focus uses a 2px action-colored outline with a 3px offset; scroll regions use an inset outline. Do not sacrifice labels or field legibility to pack more controls into a row.

## Components

### Buttons

Neutral, verb-led actions use the control fill/foreground, medium weight and minimum 44px height. Hover changes fill without a glow. Secondary and ghost actions share the working-sheet surface and quiet boundary, changing to the secondary surface on hover. Disabled controls reduce opacity and show an unavailable cursor. Destructive controls use the semantic danger family and keep an explicit label; table destructive actions use a restrained outlined treatment.

### Chips and Status Badges

Short pill annotations combine a semantic wash and foreground, with text or an icon/dot to identify meaning. Preserve actual status language. The legacy compact badge typography is an existing exception, not the standard for new labels or paragraphs.

### Cards and Containers

Flat panels use the working-sheet surface, quiet border and panel radius. Prefer section rules and shared stat strips over nesting a card for every fragment. Interior padding follows the existing spacing rhythm (commonly 20px or 24px). A dashboard Snapshot labels observed data honestly; a visual status must not imply an unverified live connection.

### Inputs and Fields

Sentence-case medium labels sit above sheet-colored controls with strong boundaries, readable field text and minimum 44px height. Focus retains the shared magenta border/wash and adds the shell's visible action outline. Error states use the danger border/wash plus text. Helper descriptions and validation stay near their field. Keep autocomplete, validation and accessible naming intact; labels remain visible rather than relying on placeholders.

### Navigation

Role/task groups use descriptive text, 44px minimum items and contained scrolling. Active items combine a sheet-colored fill, hairline inset boundary, stronger ink and a magenta icon. The collapsed rail preserves meaningful accessible names; mobile navigation traps focus while open. The utility header holds search and labelled account/notification/theme controls without competing with the page title.

### Creation Disclosures

Native details/summary blocks keep records visible while optional creation forms recede. The summary has a 56px minimum row, title, short description and an SVG chevron; its body opens inline. Preserve entered DOM state and reopen relevant panels for validation errors. Do not replace a setup form with a modal solely to save vertical space.

### Workspace Tabs and Directories

Task workspaces use 48px tabs, sentence-case labels and a 2px action-colored active underline. Tabs scroll within their region on narrow screens. The email area separates preparation, templates and delivery history into linked workspaces; review stages and provider results remain explicit rather than implying successful delivery. Tool directories use divided rows with descriptive text and real route/role visibility.

### Signature Telephony Workspace

The persistent softphone and quick-form launchers expose connection state and open bounded, resizable panels. Keep campaign context, VICIdial session state and the next call action visible; minimized tools must preserve session roots. Use the same field, action, status and overlay language as the surrounding application. Respect persisted positions and split layouts.

### Sign-in Sheet

A centered single-column layout has a maximum 440px width, configured brand/context and a flat credential panel. The panel uses 32px padding, reduced to 24px on narrow screens; its title is 24px semibold. Neutral primary actions, campaign selection, validation and theme support match the workspace materials. Preserve the existing floating-label behavior and credential semantics.

## Do's and Don'ts

### Do:

- **Do** use live semantic CSS properties and verify both themes.
- **Do** preserve configured branding, campaign context and established role terminology.
- **Do** use neutral actions with restrained magenta focus and selection.
- **Do** retain 44px targets, visible focus and readable sentence-case field labels.
- **Do** align content to the working-sheet gutter and retain the 160px bottom runway.
- **Do** contain wide tables and tabs within their own scroll regions.
- **Do** distinguish observed snapshots, stale data, connection states and delivery results honestly.
- **Do** preserve stateful call/form roots and persisted widget layouts.

### Don't:

- **Don't** restore glass, gradients, glow or decorative card lift to the operational sheet.
- **Don't** introduce a separate visual language for individual admin pages.
- **Don't** reuse tiny metadata, decorative kickers or tracked uppercase copy as a new typography standard.
- **Don't** use status colors without an accompanying label, icon or structure.
- **Don't** hide critical controls behind hover or create page-level horizontal overflow.
- **Don't** replace configured organization identity with a hard-coded product name.
- **Don't** apply these scoped workspace rules to overwrite the existing public landing composition.
- **Don't** add animation that ignores reduced-motion preference.

## Context

Taste review of the rendered 1440px page found different right-column positions: hero at 820px and roles at 775px, plus bottom-aligned supporting copy. Content repeats the call workflow in four visual treatments and sign-in links appear four times. The page is 2633px tall at the measured desktop viewport.

## Goals / Non-Goals

Goals: consistent column alignment, simpler hierarchy, one clear workflow, concise role descriptions, fewer repeated actions, responsive readability.

Non-goals: new identity, Agent Screen preview, new dependencies, backend or permission changes, fabricated marketing proof.

## Decisions

- Read this as an existing B2B CRM landing cleanup for call-center staff, retaining the charcoal/rose design system. Taste dials: variance 5, motion 3, density 4.
- One shared two-column grid with equal columns and a 32px gap aligns hero copy, the role list, and the third column of the four-step workflow. Align text blocks at their top edges. Header uses symmetric side columns to keep navigation centered.
- Keep a single ordered four-step workflow and a brief integration note with the existing telephony anchor. Remove the capability strip, separate calling section, checklist, timeline, closing sign-in section, and duplicate role subheadings. Reports remain described in the role section.
- Keep sign-in links in the header and hero. Navigation points only to retained content. Preserve branding, theme persistence, skip link, focus styles, reduced motion, and minimum 44px targets.
- Remove unused CSS for deleted sections, use readable 14px supporting text, and avoid fixed headline breaks in section headings.
- Superpowers verification principles guide evidence collection. Local Impeccable files remain unavailable; use supplied principles and existing product/design/token sources.

## Risks / Trade-offs

- Removing repeated calling content could omit integration context: retain VICIdial/Asterisk and permission caveat below the workflow.
- Shared grid could overflow narrow screens: use minmax(0, 1fr), consistent gutters, stack at tablet/mobile widths, and verify intermediate widths.
- Fewer calls to action reduce repetition: retain prominent header and hero links to the existing named route.

## Validation

Update existing landing assertions for the new content structure, remaining login links, and valid local anchor destinations. Verify both themes at 375, 768, 1024, 1440px, with intermediate widths, alignment measurements, heading/target bounds, theme interaction, keyboard focus, login navigation, and browser health. Build assets, run focused landing/login/branding tests and Pint, sync specs, and archive only once complete.

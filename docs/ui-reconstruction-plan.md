# CRM UI reconstruction plan

The user brief authorizes implementation through validation. Keep the existing Laravel/Blade architecture, all business inputs and configured branding. Scope: the complete authenticated CRM and its entry/standalone surfaces; preserve the recent public landing design.

## Design direction

Taste `design-taste-frontend` current app-screen run: **Framed sheet**, seed **325312072**, palette/type retained. This suits long campaign-scoped operations: labelled neutral navigation, a flat working sheet, ink-first data, restrained magenta focus/selection and semantic operational states. The primary product signature is the persistent campaign context and call/form workspace, not decoration. Impeccable mode: **Operate**, code-led under the existing `comp` preference's explicit Operate exception.

Objects: campaigns, staff, leads, dynamic forms/fields, submissions, calls, dispositions, attendance events, email campaigns/recipients/documents, integration servers. Figures always come from existing backend data. Synthetic fixtures are test evidence only, never replacement production data.

## Direction contract

THESIS: Campaign-scoped call-center work gets the next operation before setup. Long forms recede into native disclosures, while existing records and real operational states stay visible.

OWN-WORLD: The user pins the incumbent neutral, dual-theme Blade system and magenta identity. Flat 10px panels, 7px controls, platform sans, sentence-case labels, tabular figures and 44px targets extend that system.

STORY: Staff enter a campaign, find their role's task group, work with records or the agent rail, and reach configuration deliberately. Email preparation leads to mapping review and then provider results.

FIRST VIEWPORT: A 248px labelled sidebar, 64px utility header and aligned 32px desktop gutters frame the working sheet. Management KPIs and directories remain visible while optional layout editing is collapsed. At 375px the navigation folds away, gutters are 16px and call controls precede capture fields. Persistent launchers use a compact corner stack and 160px of bottom scrolling space.

FORM: Code-led Operate extension of a brief-pinned incumbent system; no generated comp or replacement visual world. Taste app style Framed sheet, seed 325312072; palette/type retained. Impeccable concept selection is unnecessary because the brief pins the system.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Screen questions

| Surface | First question / next task |
| --- | --- |
| Entry | Which campaign am I signing into? |
| Dashboard | What happened in this campaign and period? |
| Management | What records exist and which tool do I need? |
| Setup lists | Which account/campaign/form/connection should I edit? |
| Agent Screen | Who am I handling and what is the next call or capture action? |
| Reports/supervision | Is the campaign operating normally, and how fresh is this data? |
| Attendance | Which session/status is current? |
| Records/extraction | Which scoped records match and what can I export? |
| Email preparation | Which message and recipients should be reviewed? |
| Email review/delivery | Is this mapping safe and what did the provider accept? |
| Settings | Which configuration governs this workflow? |

## Execution sequence

1. Audit actual routes, screens, permissions and reusable components. Capture synthetic before views.
2. Refine shared tokens, content sheet, controls, table regions and form groups; preserve both themes.
3. Regroup actual navigation destinations, keeping roles/feature gates. Reuse its metadata in the management directory.
4. Add native creation disclosures to setup lists; preserve DOM input and restore after relevant validation failures.
5. Divide email preparation, templates and delivery history into directly linked workspaces; show honest review stages.
6. Refine dashboard, reports, agent rail and settings hierarchy through focused markup/shared styling.
7. Run existing and new functional tests, JavaScript tests, Vite and PHP lint. Validate every accessible page family with synthetic data in Playwright at 375/768/1280/1440 in light/dark; inspect screenshots and keyboard interactions.
8. Record parity evidence, measured results and unverified dependencies. Sync OpenSpec; archive only if all important tasks pass.

## Alternatives considered

- Wholesale component/framework rewrite: unnecessary risk to telephony lifecycle and dynamic fields.
- CSS-only restyle: fails to address workflow hierarchy and long forms hiding records.
- Chosen: shared visual refinement plus targeted task/navigation reconstruction with existing request contracts.

## Acceptance and rollback

No route or schema changes. No live calls/emails. Record-first lists, role-appropriate navigation and distinct email tasks must work using existing endpoints; every reconstructed view must be visually inspected. Existing stateful roots must survive content navigation. Rollback is the source diff. External services and failures remain explicit in the validation document and do not count as passed.

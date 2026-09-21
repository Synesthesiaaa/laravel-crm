# CRM Shared Shell and Activity Log Modernization

The approved first milestone establishes a system-wide visual foundation without changing business workflows. The application keeps its Laravel Blade, Tailwind CSS 4, Alpine.js, persistent sidebar/header, soft-navigation, theme toggle, and telephony state architecture.

The implementation will preserve existing semantic token names while moving the surfaces toward a calmer navy/slate CRM palette with an accessible blue primary accent. Shared controls will gain consistent focus, pressed, disabled, touch-target, overflow, and reduced-motion behavior. The sidebar will retain its desktop fixed/off-canvas responsive model but gain clearer grouping, active-state semantics, and mobile affordances. The header and main content will gain responsive gutters and keyboard-oriented landmarks.

The Activity Log will remain a terminal-style audit console. Its existing `activityLogTerminal()` behavior, Echo subscription, polling fallback, filtering endpoint, follow/pause/clear controls, redaction, and Alpine teardown remain intact. Only the presentation changes: filters become mobile-first, desktop entries retain aligned columns, and phone entries use stacked metadata/details with bounded wrapping for long values.

Validation covers focused render/behavior tests, the Vite build, and Playwright checks at 375, 768, 1024, and 1440px in both themes. The primary regression risks are soft-navigation double initialization, theme contrast, and long audit values creating document-level overflow.

Full proposal, requirements, design decisions, and implementation tasks are recorded in [the OpenSpec change](../../openspec/changes/modernize-responsive-crm-ui/).

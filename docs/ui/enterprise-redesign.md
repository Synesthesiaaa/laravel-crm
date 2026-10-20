# Enterprise interface redesign

Implementation and validation record, 2026-10-20. This is a presentation and interaction change on the existing Laravel 12 / Blade / Alpine / Tailwind / ApexCharts stack.

## Direction and boundaries

Taste app-screen direction: **Achromatic ledger**, seed **1283711353**, retaining the existing palette and system typography. One shell redraw replaced the dealt icon rail with labelled navigation because the CRM has more than twenty destinations. The brief overrides the direction's near-black-only action rule: the primary magenta identity and important magenta actions remain. Its face is the campaign identity and magenta data series; its signature is the campaign-scoped lead, call and disposition workspace.

No database migrations, route definitions, controllers, policies, queue handlers, SIP events, dialing transitions, report calculations, or mail transports were changed. Preview statistics are synthetic data in a separate SQLite database and never appear as production defaults.

## Design audit

| Priority | Finding | Implementation |
| --- | --- | --- |
| P0 | Linked KPI cards relied on mouse-only inline navigation. | Native links preserve the metric text, keyboard activation, and browser link actions. |
| P0 | Confirm and slide-over dialogs lacked complete names/semantics; shared dialogs had uneven Escape behavior. | Named dialogs, modal semantics, background isolation, Escape cancellation, focus handling. Mandatory call disposition remains mandatory. |
| P0 | Recording lookup, field creation, VICIdial field selection and dynamic select/percentage labels were not associated with controls. | Persistent labels and explicit IDs, with shared recording filter markup. |
| P0 | A failed search was indistinguishable from an empty result; responses could arrive out of order and arrow keys did not focus links. | Latest-request sequencing, a distinct failure with retry, native result-link focus, clear-on-close, and input focus on reopen. |
| P0 | Light-theme semantic text used bright signal colors; destructive buttons used white on bright red. | Foreground-specific semantic tokens throughout affected templates; darker destructive action fill; stronger field borders. |
| P1 | Large lead KPI, colored icon tiles and animated cards displaced operational information. | A compact four-column signal row, plain metric labels/figures, flat resting surfaces, restrained charts, and no staggered content entrances. |
| P1 | Dashboard and management headers claimed Online/Live without a verified connection state. | Removed those unconditional badges; actual integration state UI is retained. |
| P1 | Mobile Agent Screen placed call controls after a long form. | Essential controls precede lead entry on compact screens; advanced tools follow the form. Desktop rail remains visible with internal scrolling. |
| P1 | Floating minimized widgets could cover form actions. | Compact-screen launchers share a bottom dock and content reserves bottom space; desktop dragging and split view remain available. The phone iframe remains mounted when minimized. |
| P1 | Narrow headers could squeeze actions; grids could expand during chart resizing. | Wrapping header actions, constrained grid tracks/chart containers and internal table scrolling. |
| P1 | Scrollable tables were not consistently keyboard reachable. | Focusable named regions, native table semantics, column scope and active sort announcements. |
| P2 | Inconsistent radii, small labels, resting shadows and login effects weakened consistency. | Shared tight radii, sentence-case form/table labels, tabular figures, opaque surfaces and persistent login labels. |

## Module changes and preserved behavior

| Module | Improvement | Important files | Preserved behavior |
| --- | --- | --- | --- |
| Shell/navigation | 248px labelled sidebar, quieter selected row, clearer groups, wrapped page actions, mobile focus restoration | `resources/css/app.css`, `resources/views/layouts/{app,sidebar}.blade.php`, `resources/views/components/page-header.blade.php` | Role filtering, campaign forms, saved collapse, account actions and theme persistence |
| Dashboard/management | Compact signals, clear zeros/missing values, no unsupported connection badge, flat charts/cards | `resources/views/{dashboard,admin/dashboard}.blade.php`, `resources/views/components/{stat-card,admin/tool-grid}.blade.php` | Counts, currencies, date ranges, attribution, section order/visibility and drill-downs |
| Agent/phone/form widgets | Discoverable compact call controls, sticky desktop rail, bottom-docked minimized launchers | `resources/views/agent/index.blade.php`, `resources/js/{phone-widget,quick-form-widget}.js` | SIP/iframe lifecycle, dialing, hangup, disposition, lead tools, desktop layout persistence and split view |
| Reports | Named recording filters, shared filter partial, bounded charts and theme updates | `resources/views/reports/index.blade.php`, `resources/views/reports/partials/recording-*.blade.php`, `resources/js/app.js` | Data sources, report formulas, filtering, exports and unavailable-source messages |
| Administration | Shared panel/control/table language and accessible semantic text across staff, records, campaigns, extraction, configuration and audit pages | Shared CSS/components and affected `resources/views/admin/` templates | Authorization, CRUD routes, submitted fields and all existing actions |
| Field Logic/Agent Configuration | Explicit field-label relationships | `resources/views/admin/{field_logic,agent_screen}.blade.php` | Field definitions, conditional display, mappings and validation |
| Email | Quiet real-count summaries, wrapping actions, readable status/error text | `resources/views/admin/email-campaigns/{index,review}.blade.php` and shared components | Review, fail-closed document assignments, queued versus accepted distinctions, SMTP secret handling and send confirmation |
| Login/dynamic forms | Persistent credential labels, reduced visual noise, associated dynamic labels and isolated review dialog | `resources/css/app.css`, `resources/views/forms/_content.blade.php` | Campaign selection, credential behavior, old input, conditional fields, draft/review/save behavior |
| Operations Insights | Flat panels, readable labels, straight/flat chart styling | `resources/views/operations-insights.blade.php` | Existing public route boundary, data endpoints and calculations |

## Shared component states

- Buttons: clear default/hover/pressed states, visible keyboard focus, disabled cursor and existing submission/loading text. Destructive actions keep confirmation.
- Inputs/selects/textareas: persistent labels, 44px minimum control height, stronger outlines, theme-aware placeholder text, existing invalid/help associations and preserved old input.
- Cards/KPIs: opaque token surfaces, one quiet border, no resting shadow; linked cards are real anchors. Loading metrics expose `aria-busy` and text; zeros remain zeros and missing data remains a dash.
- Tables: 12px sentence-case headings, tabular figures, explicit sort direction and named keyboard-scrollable regions; no operational columns removed.
- Dialogs/panels: named modal semantics, bounded content, inert background, focus trapping/restoration and Escape handling where dismissal is allowed. Call wrap-up cannot be dismissed to skip disposition.
- Search: initial/recent, loading, result, empty, error and retry states; stale responses cannot replace a newer query.
- Notifications/reports/email: existing server-confirmed success, error, stale, pending, provider acceptance and unavailable states are retained.
- Themes/motion: both themes use the same semantic roles. Chart presentation follows the active theme without replacing series. Reduced-motion rules remain; page entrances and loading shimmer were removed.

## Validation

See `validation.md` for exact results and limitations. Browser coverage uses synthetic users and records in an isolated localhost SQLite preview. The full-page inventory below records shared-system coverage; it does not imply that every external integration or every destructive action was executed.

## Skill compliance

Authorized: Taste (installed as `design-taste-frontend`, tasteskill v2) and `ponytail`; both were read and applied. No subagents or extra skill dependencies were used.

Compliance exception: OpenSpec and Impeccable instruction files were mistakenly loaded in the initial inspection before the attachment's allowlist was read. Their workflows were not executed; no further unauthorized skills were loaded. The latest brief superseded the earlier OpenSpec/Impeccable workflow instructions.

## Template inventory

### admin

| Template | Shared components / surface |
| --- | --- |
| `resources/views/admin/activity_log.blade.php` | icon, page-header |
| `resources/views/admin/agent_screen.blade.php` | icon, page-header, validation-errors, alert, form.select, form.input, form.checkbox, form.textarea, table.index, table.head, badge, table.empty |
| `resources/views/admin/attendance_logs.blade.php` | icon, page-header, attendance.realtime-table, stat-card, app-live-clock, form.input, form.select, table.index, table.head, table.empty, attendance.event-badge, attendance.event-time |
| `resources/views/admin/attendance_status_types.blade.php` | icon, page-header, validation-errors, form.input, table.index, table.head, badge, table.empty |
| `resources/views/admin/campaigns.blade.php` | icon, page-header, validation-errors, admin.panel, form.input, form.checkbox, table.index, table.head, badge, table.empty |
| `resources/views/admin/capture_records.blade.php` | icon, page-header, validation-errors, alert, form.select, form.input, table.index, table.empty, slot, table.pagination |
| `resources/views/admin/capture_records_edit.blade.php` | icon, page-header, validation-errors, alert, form.input, form.textarea, form.select, form.checkbox |
| `resources/views/admin/configuration/branding.blade.php` | brand |
| `resources/views/admin/configuration/diagnostics.blade.php` | icon, alert |
| `resources/views/admin/configuration/disposition.blade.php` | alert |
| `resources/views/admin/configuration/general.blade.php` | stat-card, alert |
| `resources/views/admin/configuration/retention.blade.php` | alert, table.index, table.head, badge, table.empty |
| `resources/views/admin/configuration/telephony.blade.php` | alert |
| `resources/views/admin/configuration.blade.php` | icon, page-header, admin.tabs, alert |
| `resources/views/admin/dashboard.blade.php` | icon, badge, stat-card, empty-state, admin.tool-grid |
| `resources/views/admin/data_master.blade.php` | icon, page-header, validation-errors, alert, form.select, table.index, table.empty, slot, table.pagination |
| `resources/views/admin/data_master_edit.blade.php` | icon, alert, validation-errors, page-header |
| `resources/views/admin/disposition_codes.blade.php` | icon, page-header, validation-errors, form.select, form.input, table.index, table.head, badge, table.empty |
| `resources/views/admin/disposition_records.blade.php` | icon, page-header, form.input, form.select, table.index, table.head, table.empty, badge, slot, table.pagination |
| `resources/views/admin/email-campaigns/index.blade.php` | icon, page-header, validation-errors, alert, admin.panel, table.index, table.head, badge, table.empty, slot, table.pagination |
| `resources/views/admin/email-campaigns/partials/delivery-options.blade.php` | Shared tokens / included template |
| `resources/views/admin/email-campaigns/partials/document-uploads.blade.php` | Shared tokens / included template |
| `resources/views/admin/email-campaigns/review.blade.php` | page-header, table.index, table.head, slot, table.pagination |
| `resources/views/admin/email-configuration.blade.php` | icon, page-header, alert, badge, admin.panel, form.input, form.select |
| `resources/views/admin/extraction.blade.php` | icon, page-header, validation-errors, form.input, form.select |
| `resources/views/admin/field_logic.blade.php` | icon, alert, validation-errors, form.select, form.textarea, table.index, table.head, badge, table.empty |
| `resources/views/admin/field_logic_edit.blade.php` | icon, alert, validation-errors, form.select, form.textarea |
| `resources/views/admin/forms.blade.php` | icon, alert, validation-errors, page-header, admin.panel, form.input, table.index, table.head, badge, table.empty, modal |
| `resources/views/admin/lead_hopper.blade.php` | icon, page-header, validation-errors, alert, stat-card, table.index, table.head, badge, table.empty |
| `resources/views/admin/partials/dashboard-layout-editor.blade.php` | badge |
| `resources/views/admin/records_list.blade.php` | icon, page-header, form.input, table.index, table.head, table.empty, badge, slot, table.pagination |
| `resources/views/admin/supervisor.blade.php` | icon, page-header |
| `resources/views/admin/telephony-monitor.blade.php` | icon, page-header |
| `resources/views/admin/users.blade.php` | icon, page-header, validation-errors, admin.panel, form.input, form.select, admin.user-telephony-fields, table.index, table.head, badge, slot, table.pagination |
| `resources/views/admin/vicidial_servers.blade.php` | icon, page-header, validation-errors, admin.panel, form.select, form.input, table.index, table.head, table.empty, badge |

### agent

| Template | Shared components / surface |
| --- | --- |
| `resources/views/agent/capture_webform.blade.php` | Shared tokens / included template |
| `resources/views/agent/index.blade.php` | icon, empty-state |
| `resources/views/agent/partials/callback-form.blade.php` | Shared tokens / included template |
| `resources/views/agent/partials/dtmf-keypad.blade.php` | Shared tokens / included template |
| `resources/views/agent/partials/ingroup-panel.blade.php` | Shared tokens / included template |
| `resources/views/agent/partials/lead-search.blade.php` | Shared tokens / included template |
| `resources/views/agent/partials/recording-controls.blade.php` | icon |
| `resources/views/agent/partials/transfer-panel.blade.php` | Shared tokens / included template |

### attendance

| Template | Shared components / surface |
| --- | --- |
| `resources/views/attendance/index.blade.php` | icon, page-header, attendance.event-badge, app-live-clock, attendance.status-panel, form.input, table.index, table.head, table.empty, attendance.event-time |

### auth

| Template | Shared components / surface |
| --- | --- |
| `resources/views/auth/login-pending.blade.php` | brand |
| `resources/views/auth/login.blade.php` | brand, icon |

### components

| Template | Shared components / surface |
| --- | --- |
| `resources/views/components/admin/panel.blade.php` | Shared tokens / included template |
| `resources/views/components/admin/tabs.blade.php` | icon |
| `resources/views/components/admin/tool-grid.blade.php` | icon |
| `resources/views/components/admin/user-telephony-fields.blade.php` | form.input |
| `resources/views/components/alert.blade.php` | icon |
| `resources/views/components/app-live-clock.blade.php` | Shared tokens / included template |
| `resources/views/components/attendance/event-badge.blade.php` | badge |
| `resources/views/components/attendance/event-time.blade.php` | Shared tokens / included template |
| `resources/views/components/attendance/realtime-table.blade.php` | icon, table.index |
| `resources/views/components/attendance/status-panel.blade.php` | icon, badge, empty-state |
| `resources/views/components/badge.blade.php` | Shared tokens / included template |
| `resources/views/components/brand.blade.php` | icon |
| `resources/views/components/breadcrumbs.blade.php` | icon |
| `resources/views/components/click-to-call.blade.php` | click-to-call, icon |
| `resources/views/components/dropdown.blade.php` | Shared tokens / included template |
| `resources/views/components/empty-state.blade.php` | icon |
| `resources/views/components/form/actions.blade.php` | icon |
| `resources/views/components/form/checkbox.blade.php` | Shared tokens / included template |
| `resources/views/components/form/group.blade.php` | Shared tokens / included template |
| `resources/views/components/form/input.blade.php` | icon |
| `resources/views/components/form/select.blade.php` | icon |
| `resources/views/components/form/textarea.blade.php` | icon |
| `resources/views/components/icon.blade.php` | Shared tokens / included template |
| `resources/views/components/loading.blade.php` | icon |
| `resources/views/components/modal.blade.php` | icon |
| `resources/views/components/page-header.blade.php` | breadcrumbs |
| `resources/views/components/slide-over.blade.php` | icon |
| `resources/views/components/stat-card.blade.php` | icon |
| `resources/views/components/table/empty.blade.php` | icon |
| `resources/views/components/table/head.blade.php` | icon |
| `resources/views/components/table/index.blade.php` | Shared tokens / included template |
| `resources/views/components/table/pagination.blade.php` | Shared tokens / included template |
| `resources/views/components/validation-errors.blade.php` | alert |

### dashboard.blade.php

| Template | Shared components / surface |
| --- | --- |
| `resources/views/dashboard.blade.php` | icon, stat-card, empty-state, modal |

### email-campaigns

| Template | Shared components / surface |
| --- | --- |
| `resources/views/email-campaigns/unsubscribe.blade.php` | Shared tokens / included template |

### errors

| Template | Shared components / surface |
| --- | --- |
| `resources/views/errors/403.blade.php` | Shared tokens / included template |
| `resources/views/errors/404.blade.php` | Shared tokens / included template |
| `resources/views/errors/500.blade.php` | Shared tokens / included template |
| `resources/views/errors/layout.blade.php` | Shared tokens / included template |

### forms

| Template | Shared components / surface |
| --- | --- |
| `resources/views/forms/show.blade.php` | icon |
| `resources/views/forms/widget.blade.php` | Shared tokens / included template |
| `resources/views/forms/_content.blade.php` | breadcrumbs, validation-errors, icon, form.group, form.input, form.textarea |

### layouts

| Template | Shared components / surface |
| --- | --- |
| `resources/views/layouts/app.blade.php` | icon, modal, click-to-call, alert |
| `resources/views/layouts/sidebar.blade.php` | brand, icon |

### operations-insights.blade.php

| Template | Shared components / surface |
| --- | --- |
| `resources/views/operations-insights.blade.php` | brand, icon |

### partials

| Template | Shared components / surface |
| --- | --- |
| `resources/views/partials/phone-widget.blade.php` | icon |
| `resources/views/partials/quick-form-widget.blade.php` | icon |

### records

| Template | Shared components / surface |
| --- | --- |
| `resources/views/records/index.blade.php` | icon, page-header |
| `resources/views/records/partials/call-history-panel.blade.php` | icon |

### reports

| Template | Shared components / surface |
| --- | --- |
| `resources/views/reports/index.blade.php` | icon, page-header, table.index, table.head |
| `resources/views/reports/partials/agent-stats.blade.php` | Shared tokens / included template |
| `resources/views/reports/partials/call-status.blade.php` | Shared tokens / included template |
| `resources/views/reports/partials/dispo-report.blade.php` | Shared tokens / included template |
| `resources/views/reports/partials/recording-browser.blade.php` | Shared tokens / included template |
| `resources/views/reports/partials/recording-filters.blade.php` | Shared tokens / included template |

### welcome.blade.php

| Template | Shared components / surface |
| --- | --- |
| `resources/views/welcome.blade.php` | brand, icon |


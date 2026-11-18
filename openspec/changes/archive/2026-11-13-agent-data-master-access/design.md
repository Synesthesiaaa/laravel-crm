## Context

Administrative Data Master reads configured dynamic form tables and exposes write routes behind the administrator role boundary. Normal submissions store both a display-name agent and a stable user ID in crm_call_history. Campaign selection is session-based; there is no agent-to-campaign assignment relation to extend.

## Goals / Non-Goals

Goals: implement the user's selected campaign-wide settings, explicit allowed forms, read-only records, and form/search/date/column filters. Retain the current Signal Magenta Operate design using shared Blade form, tab, alert, table, and pagination components.

Non-goals: changing campaign login/assignment behavior, administrative Data Master writes, exports, retroactive ownership guessing, or dynamic storage provisioning.

## Decisions

- Reuse audited SystemSetting records with one JSON value per campaign instead of adding permission tables or campaign columns. Store enabled=false, own_submissions_only=true, and form_ids=[] by default. Read settings fresh per request to make revocation immediate. Invalid JSON fails closed.
- Use separate AgentDataMasterController and AgentDataMasterService; leave the existing administrative write boundary intact. The GET endpoint resolves only the session campaign, not a query-string campaign or table name.
- Allowed forms are active, non-deleted Form IDs belonging to an active campaign. IDs prevent new/recreated forms from inheriting an old permission. No selection means no records; enabling requires at least one active selected form.
- Own-only access uses an EXISTS predicate matching campaign, form code, record ID and authenticated user ID in crm_call_history. Legacy rows without reliable user attribution are excluded rather than matched by ambiguous names. For tables shared by multiple configured forms, campaign-wide mode also requires matching campaign/form provenance, preventing cross-campaign disclosure. Unshared tables in campaign-wide mode include all stored records.
- Schema-backed, metadata-approved columns are explicitly selected. Search OR predicates are grouped inside the permission boundary. Column filters combine with AND, accept only display columns and bounded strings, and use bound parameters. Submission date range uses created_at when present, otherwise date, with inclusive dates. Pagination preserves validated filter state.
- Reuse the existing configuration tab structure for campaign selection and three permission controls. Agent page shows campaign, read-only and visibility-scope explanations, primary filters, collapsible column filters, results, and pagination. Mobile uses stacked controls and contained horizontal table scrolling.

## Risks / Trade-offs

- Legacy rows lacking stable attribution are absent in own-only mode; Super Admin can choose campaign-wide visibility, while ambiguous shared tables remain provenance-scoped.
- Broad LIKE filters can be expensive on very large dynamic tables; bound input and paginate, without speculative indexes on arbitrary columns.
- Missing form storage returns an actionable unavailable message; do not provision tables or suppress a broken query as an empty result.

## Migration Plan

No migration. Deploy source and rebuilt frontend assets. Super Admin opens Configuration > Data Master, selects a campaign and permitted forms, chooses the ownership scope, and saves. Disable to revoke access. Rollback source removes the new surface while inert audited settings remain.

## Open Questions

None; permission granularity, form selection and filter coverage were explicitly selected by the user. Storage, safe defaults, legacy provenance handling, and configuration placement are implementation choices.

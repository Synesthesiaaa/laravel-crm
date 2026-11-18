## Why

Agents need to review campaign submissions without receiving administrative editing privileges. Super Admin must control campaign access, submission ownership scope, and visible forms.

## What Changes

- Add campaign-wide Data Master settings managed only by Super Admin: enabled, own submissions only, and explicitly allowed active forms.
- Add a separate read-only agent destination scoped to the current session campaign.
- Provide form selection, search, inclusive submission date range, and individual column filters with server pagination.
- Enforce permissions in server queries and omit editing, deleting, importing, and exporting from the agent surface.
- Start disabled with own submissions only enabled; new forms require explicit selection.

## Capabilities

### New Capabilities
- `agent-data-master-access`: Campaign-controlled, read-only access to permitted form submissions with bounded server-side filters.

### Modified Capabilities
None. Existing administrative Data Master behavior remains intact.

## Impact

New access/query services, Form Requests, controllers, Blade views, configuration tab, sidebar entry, and feature/browser validation. Reuses audited system settings and existing submission history; no new dependencies, migrations, live integrations, or changes to campaign login behavior.

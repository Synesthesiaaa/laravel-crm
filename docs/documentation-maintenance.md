# Maintaining the documentation

Documentation is part of each feature change. Keep it readable directly on GitHub; no documentation framework or service is required.

## Source of truth and ownership

Use current routes, middleware, requests/policies, controllers, services, views, configuration, migrations, and tests together. An archived proposal is evidence of intent, not evidence of shipped behavior. State whether a behavior is source-verified, exercised in an isolated test, externally unverified, limited, or planned. Record the date/runtime/provider when validating deployment-dependent behavior.

The [documentation home](README.md) owns navigation. The [email guide](EMAIL_CAMPAIGNS.md) owns detailed email instructions; bridge guides should link rather than duplicate them. Historical audit/telephony/Superpowers/OpenSpec material remains preserved and labelled by context. [PRODUCT.md](../PRODUCT.md) and [DESIGN.md](../DESIGN.md) remain design context, not authorization or deployment authorities.

## What to update

| Change | Required documentation |
| --- | --- |
| New feature | Feature catalog, audience guide, prerequisites, limitations, navigation |
| Workflow or deleted functionality | User/admin steps and FAQ; remove claims that deleted behavior is available |
| Endpoints/validation/responses | API reference, route inventory/contract appendix, webhook examples |
| Permission/scope | Role matrix, affected role guides, authorization/security references |
| Environment variable | Configuration and requirements/operations guide as applicable |
| Queue, job, listener, schedule | Worker topology, process-manager cautions, monitoring/troubleshooting |
| Important behavior/schema change | Upgrade/migration notes, backup/rollback implications, OpenSpec where used |
| Dependency/runtime | Requirements and quick start; check the lockfiles as well as declarations |
| Link/title/file move | All inbound navigation and anchors; preserve useful history |

## Lightweight pull-request checklist

- [ ] Explain the changed behavior in plain language before technical detail.
- [ ] Cross-check role **and** request/controller restrictions; do not infer access from a menu or seeded permission alone.
- [ ] Update configuration/API/worker references affected by the diff.
- [ ] Include a realistic synthetic example and a failure/recovery path where useful.
- [ ] Verify local file links, heading anchors, relative source paths, code fences, tables, and Mermaid descriptions.
- [ ] Keep command side effects explicit; never offer destructive reset steps for existing production data.
- [ ] Check examples for real credentials, personal data, live phones, signed links, private endpoints, and screenshots without synthetic provenance.
- [ ] Report actual checks and unresolved uncertainty; do not copy an old test result into a new release claim.
- [ ] Preserve historical records; sync/archive only the completed OpenSpec change under its own workflow.

## Rechecking source inventories

Use `php artisan route:list --json --except-vendor` for current route registrations and compare middleware with controller/request behavior. This is introspection, not endpoint testing. The developer API documents describe internal browser routes as well as the small Sanctum surface; do not label all `/api/*` paths token-based.

For automated checks, use existing tooling or a temporary local script to resolve Markdown targets/anchors and compare route/config names. No new CI dependency is needed. When validating a fresh installation, use an isolated database, synthetic users, disabled delivery/calling, and a recorded runtime. Never run a scheduler, worker, email/call smoke test, or migration merely to validate prose.

## Accessibility and review

Use one descriptive H1 per new guide, meaningful heading order, descriptive links, short steps, explicit status words, and compact tables. Explain diagrams in prose so they are understandable without the graphic. Use Impeccable's Read-mode principles for information hierarchy while preserving GitHub Markdown conventions. Add screenshots only from an isolated synthetic environment and provide useful alt text.

See the [modernization audit](audit/documentation-modernization.md) for known source contradictions and the validation boundary of this documentation release.

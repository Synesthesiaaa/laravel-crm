# Documentation modernization audit

Reviewed **2026-10-15**, local `main` checkout of `Synesthesiaaa/laravel-crm`. The only pre-existing uncommitted item was `openspec/changes/modernize-project-documentation/`; its proposal/design/task record was read and retained as the lifecycle record. Application source, current route registration and lockfiles outrank historical plans.

## Audit decisions

The README becomes a product introduction and navigation hub. Setup and operations move into maintained audience guides; `INSTALLATION.md` is a stable pointer. The detailed email guide remains authoritative and is expanded. Historical migration, email, telephony, audits, screenshots, design context, and OpenSpec records remain available. Related workflow subjects are consolidated into single guides rather than mirrored across many files.

Every new feature/role guide was checked against relevant routes, middleware, request/policy checks, controller/service behavior, views, and test sources. Tests were inspected, not executed. See the [inventory](documentation-inventory.md) for existing-file disposition and the [validation record](documentation-validation.md) for actual checks.

## Contradictions and findings

| Finding | Evidence | Documentation action / unresolved source condition |
| --- | --- | --- |
| PHP declaration is weaker than the lockfile | [composer.json](../../composer.json), [composer.lock](../../composer.lock): PHP `^8.2`, Symfony 8 requires 8.4+ | Requirements state PHP 8.4+ for this graph; dependencies untouched |
| Node declaration/old setup text is too broad | [package.json](../../package.json), [package-lock.json](../../package-lock.json), [.nvmrc](../../.nvmrc) | Use locked Vite engine `^20.19.0 || >=22.12.0`; remove Node 18 recommendation |
| CI PHP matrix disagrees with lockfile | [ci.yml](../../.github/workflows/ci.yml) targets 8.2/8.3 | Developer guide records incompatibility; no CI behavior changed or green run claimed |
| Composer simulates Unix extensions | [composer.json](../../composer.json) platform PCNTL/POSIX overrides | Explain actual Linux/WSL Horizon requirements; install success is not runtime readiness |
| PAMI patch application is not established | Patch plugin is require-dev; [PAMI patch](../../patches/marcelog-pami-php8-implode-fix.patch); installed EventFactory file inspected still uses reversed `implode` | Production no-dev build needs a verified patch/artifact gate; vendor untouched |
| AMI command collision and invalid service option | [AmiListenerCommand](../../app/Console/Commands/AmiListenerCommand.php), [AmiEventListenerCommand](../../app/Console/Commands/AmiEventListenerCommand.php), [Supervisor example](../../deploy/supervisor/laravel-ami-listener.conf.example) | Actual help selects persistent listener without `--reconnect-delay`; document plain command and ambiguity; service file untouched |
| Old direct-AMI narrative differs from browser dial path | [CallOrchestrationService](../../app/Services/Telephony/CallOrchestrationService.php) invokes VICIdial external_dial | Current integration guide separates AMI event/state and legacy originate job from current dial |
| Role permissions cannot be inferred from seed names | [CheckRole](../../app/Http/Middleware/CheckRole.php), [admin routes](../../routes/admin.php), [roles seeder](../../database/seeders/RolesAndPermissionsSeeder.php) | Actual matrix reflects Super Admin-only setup routes and narrower request checks |
| Team Leader writes are broader than a read-only role | Plain deletion requests versus Admin-only create/update field/disposition requests | Explicitly documented; application authorization not altered |
| Campaign context does not implement membership | [CampaignService](../../app/Services/CampaignService.php), [User](../../app/Models/User.php) | No claimed user-to-campaign assignment/tenant isolation; fallback described |
| Operations Insights and health are public surfaces | [web routes](../../routes/web.php), [Operations controller](../../app/Http/Controllers/OperationsInsightsController.php) | Access/exposure documented; QA/OM are not invented formal roles |
| Session replacement depends on database rows | [AuthService](../../app/Services/AuthService.php) directly queries sessions | Retain database-session recommendation; remove blanket Redis-session recommendation |
| Lead Hopper import currently imports form rows | [ImportLeadsCsvJob](../../app/Jobs/ImportLeadsCsvJob.php) calls FormSubmissionService | No automatic hopper/VICIdial lead-population claim; partial/retry limits described |
| Email has independent delivery topology | [queue config](../../config/queue.php), [email config](../../config/email_campaigns.php) | Dedicated consumer, heartbeat, gates, frozen transport and reconciliation explained |
| Payroll policy cannot be satisfied by uploaded originals | [email guide](../EMAIL_CAMPAIGNS.md), preflight/document services | Required-password policy blocks sending; never advise disabling a required policy |
| Scheduler list in old installation was incomplete | [console schedule](../../routes/console.php) | Current schedule table includes deletion, sync, notification receipts and email maintenance |
| Capacity and licensing claims lacked support | No benchmark evidence or standalone LICENSE file; Composer declares MIT | Remove user-capacity claim; accurately state license declaration versus missing notice |
| Migration notes describe obsolete missing features | [MIGRATION.md](../../MIGRATION.md) | Historical notice points to verified features/access/setup; original body preserved |
| Screenshots may expose operational data | Existing image provenance is unverified | Preserve files; do not embed them in new guides or create fake screenshots |

## Scope and remaining uncertainty

No application behavior, dependency, schema, live environment, service configuration, or existing data was changed. No migrations, worker/scheduler execution, test calls, email sends, private application browsing, or production data imports were performed. No unrelated OpenSpec change was closed.

Fresh Windows/Linux installation, production optimization/activation, DB engine/version compatibility, external permissions/PBX behavior, browser audio/device/network support, SMTP provider behavior, scanner/OCR availability, and restore procedures require controlled environment validation. Source-reviewed procedures are labelled accordingly. These gaps do not justify inventing feature support or changing application code inside this task.

## Follow-up priorities

1. Owners should review public endpoint exposure, campaign-isolation expectations, broad deletion access, and confidential document policy before live deployment.
2. Resolve dependency declaration/lockfile/CI alignment, actual PAMI patch application, and duplicate AMI commands in separately approved code work.
3. Record an isolated fresh-install acceptance run, role/campaign matrix checks, PBX/browser-media tests, controlled provider outcomes, and a backup restore drill.
4. Add a confirmed license notice and sanitized screenshots with provenance; keep [maintenance policy](../documentation-maintenance.md) in feature reviews.

# Maintenance and upgrades

Maintenance belongs to an authorized system operator. The commands below are procedures; they were not run against the installed environment during documentation modernization.

## Regular work

- Inspect supervised process health and queue age on every configured connection/lane.
- Review sync freshness, stale calls, unmatched events, and email blocked/uncertain states.
- Verify scheduled cleanup is intended and backups cover data before permanent deletion.
- Review public operational endpoint exposure, privileged accounts, private-file access, and credential rotation obligations.
- Monitor writable storage, log rotation permissions, backup success, and restore-drill evidence.

Consult [monitoring](monitoring.md) for thresholds and limitations. Database activity pruning, file-log retention, form retention, notification receipts, and email-document retention are separate mechanisms.

## Upgrade procedure

1. Record the current commit, PHP/Node/database versions, migration state, settings, and process topology without publishing secrets.
2. Read the release diff and migration files. Review behavior/permission/API/environment/worker changes and update the related documentation.
3. Rehearse on an isolated staging copy with external sending/calling disabled. Validate controlled synthetic workflows and backup restore.
4. Build a release from the committed lockfiles, check runtime requirements, and produce assets. Preserve the existing `APP_KEY`, protected environment, and persistent storage.
5. Back up and obtain the organization's normal change approval. Apply only reviewed migrations. Do not reseed an established deployment as a routine upgrade.
6. Activate the release, refresh configuration/routes/views under the correct environment, reload PHP when needed, and gracefully reload consumers/listeners/Reverb under supervision.
7. Verify critical flows, role boundaries, queue health, scheduler and sync freshness. Reconcile in-flight side effects before retrying anything.

## Restart and rotation

`php artisan horizon:terminate` reloads supervised Horizon. `php artisan queue:restart` asks plain workers to exit after their current job; service supervision must bring them back. AMI and Reverb need their own supervised restart/reload procedure. The dedicated email worker must read the same configuration as the web process and preserve a single consumer.

Changing an SMTP setting does not rewrite an already-frozen campaign snapshot. Do not use a credential change as proof pending mail is stopped; cancel/reconcile through the documented [email workflow](../EMAIL_CAMPAIGNS.md). Rotating `APP_KEY` requires a specific encrypted-data migration strategy; ordinary key generation is not that strategy.

## Destructive operations

`data-retention:run`, configured retention UI actions, cleanup commands, and migrations can permanently remove data. Confirm scope, policy ownership and restorable backup before execution. No retention dry-run guarantee is documented here. Never use `migrate:fresh`, `migrate:refresh`, table truncation, broad cache/queue deletion, or bulk retry as generic troubleshooting.

The historical [migration notes](../../MIGRATION.md) describe an older application state; they are not the current upgrade runbook. Use [deployment](deployment.md) and [backup/recovery](backup-and-recovery.md).

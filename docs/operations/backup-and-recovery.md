# Backup and recovery

The repository does not supply an automated backup/restore system. This is an operational procedure to rehearse in an isolated environment, not evidence of a successful restore.

## What must be recoverable

| Material | Why |
| --- | --- |
| Primary database | Accounts, configuration, form definitions/data, records, local call state/history, recipients/documents, snapshots, jobs when database-backed |
| `APP_KEY` and protected environment | Required to decrypt credentials and existing encrypted values; store separately under restricted recovery access |
| Private persistent storage | Uploaded email documents and any required private application files |
| Public uploaded branding | Organization assets that cannot be rebuilt from source |
| Exact release and lockfiles | Rebuild the application and compatible assets |
| Service/proxy/scheduler configuration | Restore process topology and network behavior |
| Redis work/state as applicable | Pending Redis jobs and coordination state may be lost even when the primary database survives |
| External PBX/provider data | Managed by their own backups and retention; CRM backup does not back up VICIdial or SMTP provider evidence |

Review [database structure](../developer/database.md) to include dynamic registered form tables. A list of Eloquent model tables alone is incomplete. Protect backups to the same confidentiality level as payroll/customer data and test access/audit controls.

## Capture procedure

1. Define recovery-point and recovery-time objectives with the data owner. The repository does not establish a universal frequency.
2. Identify all writers: web requests, general/email workers, AMI listener, scheduler, and external sync. Quiesce them or use a database/storage snapshot strategy that keeps related records and files consistent.
3. Use a reviewed database-native consistent backup procedure with credentials provided through a protected mechanism. Do not paste passwords into shell arguments or public reports. Back up the matching private files and encryption material.
4. Record time/timezone, release, database engine/version, storage locations, checksums, and whether queued/in-flight work was included. Encrypt and restrict backup transport and retention.
5. Verify backup readability and perform a restore drill. Merely creating a dump file does not verify recoverability.

## Restore drill

Restore to a separate network-isolated database and storage area. Keep email live/payroll gates off, PBX credentials unavailable, scheduler stopped, and all queue consumers stopped. Restore with the original key and compatible application release. Confirm schemas, registered form tables, sample record relationships, counts, branding, and decryption using synthetic/authorized checks.

Do not start restored email jobs automatically: an SMTP provider may already have accepted messages after the backup timestamp. Compare recipient states with provider evidence and reconcile uncertain outcomes before any retry. Likewise, restored local active-call state does not prove a remote call still exists; reconcile with PBX operators before enabling control.

Verify the intended staff accounts/roles, private-download authorization, configuration, and process topology. Record discrepancies and measured restore time. Promote only under the organization's recovery procedure after external side effects are reconciled.

## Upgrade rollback

Retain the previous application/asset release. A code rollback is safe only while the database and job payloads remain compatible. Review migrations individually; removed fields and retention-deleted rows cannot be reconstructed by reversing schema. Do not use automatic `migrate:rollback` as disaster recovery.

See [deployment](deployment.md), [maintenance](maintenance.md), and [email recovery](../EMAIL_CAMPAIGNS.md).

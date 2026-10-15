# Operations guide

These guides are for authorized system operators. They describe procedures grounded in repository source, not executed deployment, recovery, telephony, or delivery tests.

1. Prepare a release with [deployment](deployment.md) and [production configuration](production-configuration.md).
2. Configure the intended [queues, workers, AMI, Reverb, and scheduler](queues-and-workers.md).
3. Review [security and public surfaces](security.md) before exposure.
4. Establish [monitoring](monitoring.md), [backups/recovery](backup-and-recovery.md), and [maintenance](maintenance.md).
5. Use [troubleshooting](troubleshooting.md) for safe diagnosis and escalation.

Keep the web runtime, scheduler, and workers aligned on release, configuration, encryption key, runtime account, and shared cache. A passing web check does not prove a background process or external integration is working. Do not run scheduled jobs, consumers, or dial/email tests as harmless documentation checks.

Related: [configuration reference](../getting-started/configuration.md), [integrations](../integrations/README.md), [developer architecture](../developer/architecture.md), [email campaign authority](../EMAIL_CAMPAIGNS.md), [known review findings](../audit/documentation-modernization.md).

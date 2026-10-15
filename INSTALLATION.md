# Installation and deployment

Use the maintained guides for the current checkout:

- [Requirements](docs/getting-started/requirements.md): runtime versions, extensions, Windows limitations.
- [Quick start](docs/getting-started/quick-start.md): fresh, isolated development installation.
- [Installation](docs/getting-started/installation.md): Windows/Linux setup, database, seeders, assets, first account.
- [Configuration](docs/getting-started/configuration.md): environment settings and ownership.
- [Production deployment](docs/operations/deployment.md): server, permissions, release preparation, rollback.
- [Queues and workers](docs/operations/queues-and-workers.md): general, telephony, imports, AMI, Reverb, email.
- [Troubleshooting](docs/operations/troubleshooting.md): safe checks for common failures.

The previous production guide contained unverified capacity claims and obsolete runtime, scheduler, and worker instructions. Git history preserves it. These guides replace those instructions without claiming a fresh installation or production deployment was performed during the documentation review.

For an existing database, back up and inspect pending migrations first. Do not run `migrate:fresh`, development seeders, or application-key generation as an upgrade procedure.

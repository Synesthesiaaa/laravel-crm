# Developer documentation

Source reviewed on 2026-10-15. These guides describe this checkout, not a compatibility promise for external consumers. Runtime configuration, deployed database state, and external telephony behavior were not verified.

- [Architecture](architecture.md) and [project structure](project-structure.md)
- [Database](database.md)
- [Authentication](authentication.md) and [authorization](authorization.md)
- [API reference](api-reference.md), [endpoint inventory](endpoint-inventory.md), and [controller contracts](api-contracts.md)
- [Services](services.md), [events and jobs](events-and-jobs.md), and [realtime](realtime.md)
- [Testing](testing.md), [development workflow](development-workflow.md), and [extending the CRM](extending.md)
- [Integration guides](../integrations/README.md), [diagrams](../diagrams/README.md), [installation](../getting-started/installation.md), and [operations](../operations/README.md)

The inventory includes every explicitly declared application route in `routes/web.php`, `routes/api.php`, and `routes/admin.php`; framework/package endpoints are noted separately. Contract extracts intentionally preserve source wording so uncommon error and validation shapes remain visible.

## ADDED Requirements

### Requirement: Form storage columns follow registered fields

The system MUST treat non-deleted `form_fields` records, combined across all registered forms that share a storage table, as the source of truth for user-defined storage columns. A cleanup migration MUST preserve the approved framework columns `id`, `date`, `request_id`, `agent`, `created_at`, and `updated_at`, and MUST remove other physical columns that are not registered.

#### Scenario: Remove an unregistered legacy column

- **WHEN** a registered form table contains a physical column that is not an approved framework column and is not registered by any form using that table
- **THEN** the cleanup migration removes that column from the table

#### Scenario: Preserve registered fields

- **WHEN** a registered form table contains a physical column listed in `form_fields` for any non-deleted form using that table
- **THEN** the cleanup migration preserves the column

#### Scenario: Preserve framework-managed columns

- **WHEN** a registered form table contains `id`, `date`, `request_id`, `agent`, `created_at`, or `updated_at`
- **THEN** the cleanup migration preserves the column even when it is absent from `form_fields`

#### Scenario: Shared table fields are unioned

- **WHEN** two registered forms use the same table and each registers different fields
- **THEN** cleanup preserves the fields registered by both forms

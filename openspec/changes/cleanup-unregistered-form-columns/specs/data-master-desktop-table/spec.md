## MODIFIED Requirements

### Requirement: All Data Master columns remain visible

When the Data Master table is rendered, all registered form fields and approved framework-managed columns present in the record MUST remain visible. Physical columns that are not registered for the selected form and are not approved framework-managed columns MUST NOT be appended to the layout.

#### Scenario: Data Master shows configured and framework columns

- **WHEN** a Data Master record contains registered form fields and approved framework-managed columns
- **THEN** the table renders those columns in the configured field order with the framework-managed columns available as applicable

#### Scenario: Data Master excludes an unregistered physical column

- **WHEN** a Data Master record contains a physical column that is not registered for the selected form and is not an approved framework-managed column
- **THEN** the table does not render that physical column

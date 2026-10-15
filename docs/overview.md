# What is Laravel CRM?

Laravel CRM helps call-center teams keep customer conversations and the information collected during them together. A campaign is a named work area with its own forms and reporting context. A lead is someone the team may contact. A disposition is the outcome recorded after an interaction, such as a callback or a sale.

Agents sign in with a username, password, and campaign choice. They use campaign forms and, where enabled and configured, an Agent Screen with calling and lead tools. Team Leaders and administrators review records, attendance, dashboards, and reports. Super Admins configure users, campaigns, forms, integration servers, and system settings.

The CRM works with external VICIdial and Asterisk systems for telephony. Those systems require separate infrastructure, credentials, and operational support; installing Laravel alone does not provide a working telephone service. Reverb supplies browser updates, and background workers carry out queued work.

A separate Super Admin email workflow supports templates, recipient imports, generated PDFs, and carefully mapped uploaded documents. Live sending and payroll have explicit configuration gates. Provider acceptance does not prove delivery to an inbox.

## Suitable uses

- Outbound customer contact with structured forms and outcomes.
- Campaign record collection and controlled CSV extraction.
- Supervision of attendance, agent activity, and source-qualified call reporting.
- Authorized personalized document delivery where the organization's required privacy controls can be met.

## Boundaries

This is an implemented application with deployment-specific integrations, not a guarantee of production readiness or strict tenant isolation. Campaign selection is operational context; it is not a general per-user membership boundary. Some operations routes are public. See [access rules](roles-and-permissions.md), [security](operations/security.md), and [review findings](audit/documentation-modernization.md) before exposing a deployment.

Start with the [documentation home](README.md), [feature catalog](features.md), or [practical use cases](use-cases.md). Existing design context remains in [PRODUCT.md](../PRODUCT.md) and [DESIGN.md](../DESIGN.md); current code takes priority over historical statements.

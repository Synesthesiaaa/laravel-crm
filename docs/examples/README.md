# Synthetic examples

These files contain invented identities and reserved example-domain addresses. They are documentation fixtures, not authorized recipient lists or runnable campaigns.

- [Generic recipients](email-recipients.csv): `email,name` columns for the basic import path.
- [Personalized PDF manifest](email-personalized-recipients.csv): `email,name,employee_id,period,pdf_filename` columns for strict recipient-specific mapping.

The filename in a manifest must match the uploaded PDF basename exactly. No PDFs are supplied: do not create fake payroll documents from real employee data or infer assignments from names/row order. The application's downloadable templates and [canonical email guide](../EMAIL_CAMPAIGNS.md) govern current validation and policy gates.

Example configuration fragments appear in [quick start](../getting-started/quick-start.md) and [deployment](../operations/deployment.md); source-reviewed workflow maps appear in [diagrams](../diagrams/README.md). No real application screenshots, secrets, customer phones, campaign records, or provider receipts are included.

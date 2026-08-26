## 1. Configuration Storage

- [x] 1.1 Add an additive nullable `non_agent_api_url` column to `vicidial_servers`.
- [x] 1.2 Expose the attribute safely through the VICIdial server model and factory.

## 2. Administration

- [x] 2.1 Validate and persist the optional endpoint on VICIdial server create and update.
- [x] 2.2 Add clear, responsive endpoint inputs and fallback guidance to the server administration form.

## 3. Endpoint Resolution

- [x] 3.1 Prefer a selected server's explicit endpoint in shared Non-Agent API requests.
- [x] 3.2 Apply the same endpoint preference to direct Non-Agent API integrations while preserving derived fallback behavior.

## 4. Verification

- [x] 4.1 Add automated coverage for persistence, validation, explicit endpoint selection, and fallback derivation.
- [x] 4.2 Run affected PHPUnit tests and Laravel Pint.
- [x] 4.3 Synchronize the OpenSpec change with the implementation and verify it.

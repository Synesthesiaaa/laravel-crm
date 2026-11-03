## Why

CSV uploads inherit the shared five-second Axios timeout, which is suitable for short API calls but can abort a deployed file upload before it reaches the queue. The browser reports the same generic message for timeouts, connectivity failures, and server errors, obscuring the cause.

## What Changes

- Give CSV upload requests a dedicated five-minute timeout without changing global API polling deadlines.
- Distinguish client timeouts and HTTP server failures with safe recovery instructions, including checking recent imports before retrying an uncertain upload.
- Add delayed-upload browser regression coverage and real database-queue insertion/rollback tests without queue fakes.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `user-import-panel`: Allow slow uploads to reach the queue response and distinguish timeout/server failure feedback.

## Impact

Import-panel JavaScript, tests, operator documentation, and specification. No schema, queue routing, user creation, role, credential, or telephony changes. The deployed server is not connected; this fixes a reproducible client bug, not an assertion that every possible remote server failure has been identified.

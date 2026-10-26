## 1. Commands and local runtime

- [x] 1.1 Add command registration tests and assign distinct AMI listener signatures.
- [x] 1.2 Inspect queue configuration and update Windows/Unix startup profiles to migrate first and start matching workers.
- [x] 1.3 Verify startup failures stop before serving and update operational documentation.

## 2. CI and role authority

- [x] 2.1 Remove the ungated coverage fallback and preserve an inspectable coverage artifact.
- [x] 2.2 Add locked frontend install, JavaScript tests, and production asset build to CI.
- [x] 2.3 Add an isolated MySQL multi-connection reservation contention test to CI.
- [x] 2.4 Align telephony feature gating with the supported `users.role` authority and add authorization regression coverage.

## 3. Failure telemetry

- [x] 3.1 Validate and throttle the client-error route and record only bounded sanitized metadata.
- [x] 3.2 Capture throttle exceptions at the exception boundary while preserving HTTP 429 behavior.
- [x] 3.3 Add tests for safe logs, invalid payloads, rate limits, and secret/customer-data exclusion.

## 4. Phase verification and sync

- [x] 4.1 Run focused tests, CI-equivalent frontend checks, and command discovery checks.
- [ ] 4.2 Run MySQL contention against the isolated CI database; the local run is intentionally skipped outside GitHub Actions.
- [x] 4.3 Update tasks and root specifications to match verified implementation and pass strict OpenSpec validation.
- [ ] 4.4 Archive this change after the isolated MySQL contention test passes.

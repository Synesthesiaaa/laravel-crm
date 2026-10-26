# Development workflow

1. Read repository instructions, current route/controller/request/service/view/test sources, and relevant OpenSpec context. Existing code wins over historical telephony phase documents.
2. Describe the problem, current/desired behavior, scope, edge cases, security boundaries, and verification criteria. Use Superpowers planning/debugging and OpenSpec propose/apply for structured behavior changes; use Impeccable for UI work that needs design guidance.
3. Use Laravel Boost for read-only application-aware inspection when appropriate. Inspect commands before executing: application boot or diagnostic commands can touch storage or external services. Schema and data queries need a suitable authorized environment.
4. Implement the smallest focused change following existing Form Request, policy, repository, service, and Blade/Alpine patterns. Preserve unrelated working-tree changes.
5. Add behavior/regression tests where practical, fake external transports, and validate in an isolated environment. Run only relevant project-supported checks; see [testing](testing.md).
6. For UI flows, validate with Playwright and perform design critique/accessibility/responsive checks. A docs-only change does not require a running browser.
7. Review diffs, synchronize OpenSpec with the actual result, and archive only when required validation is complete. Report skipped or unverified work explicitly.

`composer setup` is an executable installation workflow that copies environment configuration, generates a key, migrates, installs frontend packages, and builds. It is not a read-only health check. `composer dev` starts an HTTP server, queue listener for telephony/default, logs, Vite and Reverb. `composer dev:email` starts a separate email worker. Starting workers can immediately perform queued remote calls or email sends.

The Composer constraint and lockfile require PHP `^8.4`. Vite's locked engine requirements narrow the generic Node >=20 declaration; use Node ^20.19 or >=22.12 for this lockfile. Composer's platform declarations for pcntl/posix are dependency-resolution settings, not proof those extensions exist on Windows.

Use configuration files and the example environment file to discover variable names. Never copy real environment values into docs or commit keys, remote passwords, SIP credentials, signed recipient URLs, or customer data. [Operations](../operations/README.md) covers process/deployment boundaries.

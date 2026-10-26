## 1. Protect reporting data

- [x] 1.1 Move Operations Insights page and data routes under the existing authenticated campaign and supervisor reporting-role middleware.
- [x] 1.2 Update feature coverage for guests, Agents, and authorized reporting roles while preserving report and record filtering behavior.

## 2. Harden VICIdial boundaries and telemetry

- [x] 2.1 Use validated lead request data and reject caller-supplied transport-owned keys at the request boundary.
- [x] 2.2 Reject reserved Non-Agent parameters for single and batch requests before issuing HTTP calls.
- [x] 2.3 Reject reserved Agent API query parameters before issuing HTTP calls.
- [x] 2.4 Redact credential-like context and credential-bearing URLs/messages before all telephony log writes, with sentinel-based tests.

## 3. Require webhook authentication

- [x] 3.1 Make missing AMI and VICIdial event POST secrets fail closed in every environment.
- [x] 3.2 Update webhook feature tests and synthetic event callers to send explicit test secrets.
- [x] 3.3 Update environment and integration documentation to state the new secret requirement.

## 4. Repair dependency and PHP runtime declarations

- [x] 4.1 Update vulnerable Composer lockfile packages within current major constraints and review package operations.
- [x] 4.2 Update vulnerable npm lockfile packages without forced upgrades and verify the build.
- [x] 4.3 Align the Composer PHP requirement, CI matrix/jobs, installation requirements, and actual PHP extension checks on PHP 8.4+.
- [x] 4.4 Add Composer and npm lockfile audits as enforced CI checks.

## 5. Verify and synchronize

- [x] 5.1 Run focused security/telephony/report tests, relevant regression suites, style checks, dependency audits, and build/platform checks; record any environment limits.
- [x] 5.2 Validate this OpenSpec change, synchronize the modified specifications, and archive only after all applicable acceptance criteria pass.

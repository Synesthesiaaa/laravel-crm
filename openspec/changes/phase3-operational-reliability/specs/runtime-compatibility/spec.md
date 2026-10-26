## ADDED Requirements

### Requirement: Frontend quality checks are part of CI
The CI workflow SHALL install frontend dependencies from the committed lockfile, run the repository's JavaScript tests, and compile production assets.

#### Scenario: Frontend tests fail
- **WHEN** the JavaScript test command exits unsuccessfully
- **THEN** the CI workflow fails

#### Scenario: Asset compilation fails
- **WHEN** the production asset build exits unsuccessfully
- **THEN** the CI workflow fails

### Requirement: Coverage failures cannot bypass the gate
The CI workflow SHALL fail when coverage generation fails and SHALL NOT replace the gated run with a plain test command.

#### Scenario: Coverage artifact is missing
- **WHEN** the coverage step does not produce its report
- **THEN** the workflow fails and does not report the suite as passing

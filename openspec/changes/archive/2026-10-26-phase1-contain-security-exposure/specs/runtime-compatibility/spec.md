## ADDED Requirements

### Requirement: Declared runtime supports the committed dependency graph
The application SHALL declare PHP 8.4 as its minimum supported runtime, and Composer installation and CI SHALL use a PHP version compatible with the committed lockfile without ignoring platform requirements.

#### Scenario: CI installs the locked application dependencies
- **WHEN** CI installs dependencies using the committed Composer lockfile
- **THEN** the job uses PHP 8.4 or 8.5, checks actual lockfile requirements including PCNTL/POSIX, and does not bypass Composer platform checks

#### Scenario: The patched PAMI dependency is installed
- **WHEN** Composer installs the locked PAMI dependency
- **THEN** Composer installs its locked source revision, applies the tracked PHP-compatible patch, and fails the install if patching fails

#### Scenario: An operator follows installation requirements
- **WHEN** an operator prepares a supported application runtime
- **THEN** Composer, CI, and installation documentation state the same PHP baseline and the application does not claim support for an incompatible version

### Requirement: Locked dependency security is verified
Composer and npm lockfiles SHALL contain compatible patched versions for advisories known to their respective audit commands, and CI SHALL fail when either audit identifies an unresolved advisory.

#### Scenario: Security audit runs against the committed lockfiles
- **WHEN** the Composer and npm security audit jobs run
- **THEN** both audit the committed lockfiles and fail for unresolved advisories rather than hiding or retrying them without a gate

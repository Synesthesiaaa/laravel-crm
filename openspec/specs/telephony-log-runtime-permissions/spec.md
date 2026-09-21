## Purpose

Keep date-rotated telephony logs writable across PHP-FPM, Horizon, the AMI listener, and scheduler processes after daily rotation and service restarts.

## Requirements

### Requirement: Telephony daily files are shared-runtime writable

The system SHALL configure the `telephony`, `telephony-events`, and `telephony-errors` daily logging channels with file permission `0664` so a newly created date-specific file grants read and write access to its owner and shared group.

#### Scenario: A telephony channel creates the first file for a new date

- **WHEN** an approved Laravel runtime process writes to a telephony daily channel after date rotation
- **THEN** the date-specific log file is created with group-write permission and the log entry is written successfully

#### Scenario: A successful VICIdial response is logged after rotation

- **WHEN** `VicidialNonAgentApiService` receives a successful HTTP response and records its request metadata
- **THEN** the logging operation does not fail solely because the date-specific telephony file was created by another approved runtime process

### Requirement: Production runtime and directory inheritance are consistent

The production deployment SHALL run PHP-FPM, Horizon, the AMI listener, and the scheduler as the `crm` application account, SHALL own Laravel writable paths with group `www-data`, and SHALL apply setgid `2775` to writable directories so newly created files inherit the shared group.

#### Scenario: The AMI listener and web process write on the same date

- **WHEN** the AMI listener creates a telephony log file before PHP-FPM records a Non-Agent API request
- **THEN** both processes can append to the file without a permission-denied logging exception

#### Scenario: A new date-specific file is created after restart

- **WHEN** an approved Laravel process creates the first telephony log file after a restart or midnight rotation
- **THEN** the file inherits the `www-data` group from `storage/logs` and remains writable by the shared runtime group

### Requirement: Production setup provides a one-time repair and validation path

The production installation guidance SHALL provide commands to repair existing `storage/logs` and `bootstrap/cache` ownership and modes, clear cached configuration as `crm`, restart managed processes, and test file creation under the actual application runtime account.

#### Scenario: The currently failing file was created with incompatible ownership

- **WHEN** an operator deploys this change on a host with existing telephony log files
- **THEN** the operator can normalize the existing files once and verify the runtime can create and remove a write-test file before resuming telephony traffic

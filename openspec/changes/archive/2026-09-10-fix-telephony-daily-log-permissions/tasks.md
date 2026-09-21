## 1. Laravel logging

- [x] 1.1 Configure the three telephony daily channels with `0664` file permissions while preserving their existing paths, levels, retention, and rotation behavior.
- [x] 1.2 Add focused PHPUnit coverage that asserts the telephony daily channels use the shared-runtime permission contract.

## 2. Production runtime configuration

- [x] 2.1 Align the checked-in AMI Supervisor example with the documented `crm` Laravel runtime account.
- [x] 2.2 Update production installation guidance with setgid writable-directory setup, existing-file repair, cached-config refresh, process restart, and runtime write-test commands.

## 3. Verification and specification closeout

- [x] 3.1 Run the focused PHPUnit test, Laravel config verification, Pint on modified PHP files, and relevant repository checks.
- [x] 3.2 Synchronize the new runtime-permissions specification into the main OpenSpec specs.
- [x] 3.3 Archive the completed OpenSpec change after implementation and validation are complete.

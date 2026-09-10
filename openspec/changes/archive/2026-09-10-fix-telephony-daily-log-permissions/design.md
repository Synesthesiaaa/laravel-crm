## Context

The `telephony`, `telephony-events`, and `telephony-errors` channels use Laravel's `daily` driver. Monolog opens the date-specific file on the first write, so a permission repair on an existing file does not control the next file created after midnight. The application is deployed with a `crm` service account and `www-data` web group, but the checked-in AMI Supervisor example currently runs the listener as `www-data`, allowing different processes to create files with incompatible ownership or mode.

The source checkout is Windows-based and cannot inspect or mutate ownership on the production Linux host. The repository can make future file creation predictable and provide the exact production bootstrap and validation steps, but the currently failing production files still need a one-time repair after deployment.

## Goals / Non-Goals

**Goals:**

- Make newly rotated telephony log files writable by the approved Laravel runtime processes.
- Use one documented Laravel runtime account (`crm`) for PHP-FPM, Horizon, the AMI listener, and the scheduler.
- Make the log directory preserve the shared `www-data` group for files created by either approved process context.
- Make the permission contract executable as a focused PHPUnit assertion and verifiable on production with a write test.

**Non-Goals:**

- Changing VICIdial request handling or treating an HTTP 200 response as a telephony failure.
- Changing log retention, log contents, log destinations, or the public API.
- Having Laravel attempt to repair Linux ownership during an HTTP request.

## Decisions

### Use Laravel's daily-channel permission option

Set `permission` to octal `0664` on the three telephony daily channels. Laravel passes this value to Monolog's `RotatingFileHandler`, which applies it after opening a newly created file. This preserves daily rotation and retention while ensuring the file itself grants group write access.

An application-level permission value alone cannot open an already inaccessible file, so it is paired with the directory ownership and inheritance rules below.

### Standardize application processes on `crm`

Use `crm` for PHP-FPM, Horizon, the AMI listener, and the scheduler. This matches the installation guide and avoids an AMI process creating a telephony file under a different owner. The Supervisor example is updated to remove the current `www-data` mismatch.

### Preserve a shared group at the directory boundary

Own the writable Laravel paths as `crm:www-data`, make writable directories setgid `2775`, and make existing writable files `0664`. The setgid bit makes newly created files inherit `www-data` as their group even when the creator's primary group differs. The one-time repair also normalizes files already created with the wrong owner or mode.

Alternative: run the AMI listener and PHP-FPM under `www-data`. This would solve one user mismatch but would diverge from the existing `crm` deployment model and the Horizon and scheduler configuration, so it is not selected.

Alternative: replace file logging with syslog or stderr. That would avoid filesystem ownership but would remove the application's documented date-specific telephony log files and change operational log collection, so it is out of scope.

## Risks / Trade-offs

- [Existing production files remain inaccessible until repaired] → Run the documented `chown`/`chmod` repair once after deploying the change, then run the actual runtime write test.
- [A process outside the approved runtime still creates files in the directory] → Setgid inheritance and `0664` reduce the failure surface, while the deployment guidance makes the approved process identity explicit.
- [Group-writable telephony logs can be modified by members of `www-data`] → Limit the permission change to telephony logs and retain the existing server group boundary; do not broaden it to security or audit channels.

## Migration Plan

1. Deploy the source changes and clear the cached Laravel configuration with the `crm` account.
2. Repair `storage/logs` and `bootstrap/cache` ownership and modes once on the production host.
3. Restart PHP-FPM and Supervisor-managed Laravel processes so all long-running processes use the corrected configuration and account.
4. Run the documented `sudo -u crm` write test, then exercise one telephony request and verify the new date-specific file is group-writable.
5. Roll back the source files if required; retain the shared directory ownership and setgid permissions because they are compatible with the previous channel configuration.

## Open Questions

None for the repository change. The production operator must confirm that PHP-FPM and Supervisor are actually configured to run the Laravel application as `crm` before declaring the deployment verified.

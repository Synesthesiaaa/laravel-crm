## Why

The telephony logger rotates into a new date-specific file each day, but the web process, queue workers, scheduler, and AMI listener are not consistently documented to run under one shared runtime identity. The first process to create the new file can therefore leave it unwritable to the next process, causing a successful VICIdial request to produce a daily Laravel logging exception.

This change makes daily telephony log creation safe for the application processes and documents the one-time production repair and deployment rules needed to keep that guarantee across rotation and restarts.

## What Changes

- Configure the telephony daily log channels to create files with group-write permissions.
- Align the checked-in AMI Supervisor example with the application runtime user used by PHP-FPM, Horizon, and the scheduler.
- Update production permission guidance to use a shared group, setgid writable directories, and a runtime write test so new daily files inherit usable ownership and permissions.
- Add automated coverage for the telephony logging permission configuration.

## Capabilities

### New Capabilities

- `telephony-log-runtime-permissions`: Defines the ownership, directory inheritance, and file permission contract for date-rotated telephony logs.

### Modified Capabilities

## Impact

- `config/logging.php` and the telephony daily log channels.
- `deploy/supervisor/laravel-ami-listener.conf.example` and `INSTALLATION.md` production setup guidance.
- PHPUnit configuration coverage; no API or database changes.
- The existing production log directory and current date-specific files require a one-time ownership and mode repair after deployment; the application cannot change Linux ownership by itself.

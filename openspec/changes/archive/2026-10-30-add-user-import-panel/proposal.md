## Why

Super Admins need to import staff from the User Access page without shell access. Large files require a persistent, resumable background workflow rather than a long web request.

## What Changes

- Add an inline CSV upload/review/progress area to User Access, with template and safe row-error downloads.
- Run preview and confirmed creation as bounded database-queue jobs with persistent checkpoints, transactional duplicate tracking, and server-side role/ownership enforcement.
- Store source files privately and delete them on completion, cancellation, failure, or expiry.
- Reuse the existing importer rules, hashing, encryption, model events, and disabled VICIdial provisioning. Keep the CLI as an optional operational interface.

## Capabilities

### New Capabilities

- `user-import-panel`: Super Admin CSV upload, background preview, explicit import, progress, cancellation, safe error downloads, and retention.

### Modified Capabilities

None. Existing CSV account validation/creation behavior is preserved.

## Impact

Admin routes/controller/request, importer checkpoint adapter, database queue job and transient import tables, private filesystem disk, Users Blade/Alpine component, scheduler cleanup, tests, and operator docs. No new package. No production import or external provisioning is performed during development.

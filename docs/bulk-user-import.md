# Bulk CRM user import

## Import from User Access

Sign in as a **Super Admin** and open **Super Admin → User Access**. The **Import users from CSV** panel provides the complete workflow without a terminal:

1. Download the CSV template, replace all sample values/passwords, and choose your UTF-8 CSV.
2. Keep **Allow roles above Agent** unchecked for ordinary staff. Enable it deliberately for CSVs containing Team Leader, Admin, or Super Admin accounts.
3. Select **Upload and preview**. This queues validation and creates no accounts.
4. Review eligible, skipped, and rejected counts. Download row errors or expand the first ten issues. Correct the file and upload again if necessary.
5. Select **Import eligible users** to create valid accounts. Rejected rows are left out, and existing usernames are skipped.
6. You can leave the page and return to its recent imports. When complete, select **Refresh user list**. **Cancel import** stops future batches and retains already-created accounts; **Discard upload** removes an unused preview.

Uploads are available only to their submitting Super Admin. The server rechecks access for every request and background batch. Source CSVs have no download endpoint, are stored on a private non-serving disk, and are removed when the import completes, fails, is cancelled, or expires. Abandoned uploads expire after 24 hours; hourly cleanup removes expired/orphan sources. Safe reports and metadata are retained for seven days. The browser never stores CSV credentials in local/session storage.

The application supports files up to **20 MiB**, subject to PHP/web-server upload limits; the panel displays the effective limit on the current server. Workers create at most **50 CSV records per reservation**, preserving checkpoints and duplicate tracking in the same transaction as account writes. Browser reloads and queue retries resume from committed checkpoints. Transient database locking/deadlocks retry the unchanged checkpoint rather than stopping the import. Preview and import can differ if accounts change between phases; database uniqueness remains authoritative. No VICIdial users/phones are provisioned.

### Server setup

Deploy the code and apply the additive migration:

```bash
php artisan migrate --force
```

Run a supervised consumer for the dedicated **database-backed** connection and queue:

```bash
php artisan queue:work user-imports --queue=user-imports --sleep=1 --tries=0 --timeout=120
```

This queue is separate from the global queue connection and existing Redis/Horizon import lane. A sync default never forces imports into the upload request. Jobs release themselves between batches; `tries=0` permits the needed reservations. Reservation timeout is 360 seconds, above the 120-second job timeout. Web and worker must share the application database, release, key, and private source disk.

Enable the existing Laravel scheduler; it invokes `users:imports-prune` hourly. You can also run that command directly to clean up expired user imports without executing unrelated scheduled tasks. A queued/stale UI state is truthful when its worker is absent; it does not imply accounts were created. Production worker timeout/signal validation still requires a compatible supervised runtime.

For the full 20 MiB application allowance, set PHP `upload_max_filesize=20M` and `post_max_size=21M` or greater, and align proxy/web-server body limits. The local CLI PHP configuration observed during implementation was `2M`/`8M`; it was not changed. The isolated browser fixture used explicit 20M/21M overrides. Restrict filesystem access to `storage/app/private/user-imports` and keep it outside public storage/symlinks.

## Optional command-line importer

Run this Artisan script from the CRM directory using a trusted operator account with shell/database access. It imports **new CRM accounts** from a local UTF-8 CSV, one record at a time in bounded batches. No web upload or queue worker is required.

## Prepare the file

Copy `docs/examples/users-import.csv` into a private directory outside `public` and replace every sample value, including each password. Store CSVs with restricted filesystem access: they contain plaintext credentials. Remove them when finished. The sample uses fictional `example.test` email addresses and does not represent real accounts.

Required header:

```csv
username,full_name,password
agent001,Agent One,ReplaceWithUnique1!
```

Optional headers:

| Column | Behavior |
| --- | --- |
| `email` | Defaults to `username@<host from APP_URL>`; must be a valid unique email. Supply it explicitly if the username is unsuitable for email. |
| `name` | Defaults to full name. |
| `role` | Defaults to `Agent`. Exact alternatives: `Team Leader`, `Admin`, `Super Admin`; these require `--allow-privileged-roles`. |
| `vici_user`, `vici_pass` | Existing VICIdial login credentials stored in the CRM. Leading zeroes in identifiers are preserved. |
| `extension`, `sip_password` | SIP profile; secrets use the existing model encryption. |
| `default_campaign` | Existing campaign code, stored as text using the normal user creation behavior. |
| `auto_vici_login` | Defaults to false. |
| `default_blended` | Defaults to true. |
| `default_ingroups` | Optional comma-separated ingroup names; quote the CSV field when it contains commas. |

Booleans accept `true/false`, `1/0`, `yes/no`, or `on/off`, ignoring case. Blank optional telephony values become null. Password fields preserve whitespace. CSV headers ignore surrounding whitespace/case; unknown or duplicate headers are rejected. A UTF-8 BOM, quoted commas, and quoted multiline fields are supported. Use a comma delimiter and standard doubled CSV quotes. Export identifier columns as text in spreadsheet tools to preserve leading zeroes.

CRM passwords require at least 8 characters with uppercase, lowercase, and a number, matching the admin form. Bcrypt passwords must fit within 72 **bytes** to avoid silent truncation; other configured hash drivers are limited to 4096 bytes. SIP passwords, if provided, require at least 4 characters. Other field limits match the existing admin form.

## Preview, then import

```powershell
php artisan users:import "C:\private\users.csv" --dry-run --errors="C:\private\users-preview-errors.csv"
php artisan users:import "C:\private\users.csv" --chunk=500 --errors="C:\private\users-import-errors.csv"
```

The report path must be new; the script never overwrites an existing file. Omit `--errors` to use console diagnostics only. Reports contain CSV **record numbers** (header is record 1, including blank CSV records), field/rule names or static diagnostics, and no source values or credentials. Only the first ten row errors are printed to the console. Use `-v` to display progress after each batch.

For a reviewed file that intentionally includes higher-privilege accounts:

```powershell
php artisan users:import "C:\private\users.csv" --allow-privileged-roles
```

Dry runs report `Eligible` instead of `Created` and persist no CRM users/activity records. They read the CRM database and use a temporary local index for duplicate detection. They do not hash credentials, test external connectivity, or guarantee that a later import will succeed if application configuration or concurrent data changes.

## Duplicates, interruptions, and exit codes

- Existing usernames, including soft-deleted accounts, are skipped without updating or restoring them. Repeated valid usernames in the CSV are also skipped, across batches.
- Existing or repeated email addresses belonging to another candidate are rejected. Only successfully created/eligible candidates reserve an input identity; invalid rows do not block corrected later rows.
- Each successful account commits independently. Rejected rows do not stop other rows. Database/runtime/report failures stop the import, retain already completed accounts, and print safe counts. Rerun the same CSV to skip completed usernames. A fatal stop can leave processed rows that were read but never attempted.
- Validation runs before duplicate skipping; an already-existing username with invalid fields is reported as rejected. Rerunning an unchanged valid CSV is safe.
- Exit `0`: no rejected rows (duplicates alone are allowed). Exit `1`: rejected rows or runtime failure. Exit `2`: invalid options, unreadable file, invalid header, or unavailable report path.

## Runtime and scale

The command requires the normal CRM database, `APP_KEY`, `pdo_sqlite`, and writable OS temporary storage. It creates a temporary SQLite index containing identity hashes only, then deletes it on completion/error. A forcibly killed process can leave a `crm-user-identities-*` temporary file, which can be removed after confirming no importer is using it.

`--chunk` defaults to 500 and accepts 1–1000. Database identity lookups and PHP memory are bounded by batch size; the identity index grows on disk. The input is streamed rather than loaded in full. Console diagnostics are capped and query logging is disabled for the command. A single CSV record still needs to fit into memory.

Accounts use the normal `UserService` and Eloquent events, retaining activity logs, password hashing, and encrypted secrets. Per-account hashing and transactions deliberately preserve those guarantees, so production throughput depends on hashing configuration and database latency. This is not a raw SQL loader and does not reduce password security for speed.

Input identity hashes compare trimmed, case-folded text; the actual database unique indexes remain authoritative. Database collation differences (for example accent-insensitive MySQL indexes) and concurrent imports can produce different dry-run counts. Live unique collisions are handled without updating accounts. MySQL concurrency behavior requires validation on the deployment database.

VICIdial users/phones are **not provisioned** by this command. Imported telephony profile values can be used by existing CRM login flows. The admin web form keeps its existing provisioning behavior. No invitations, password resets, or other messages are sent by the importer.

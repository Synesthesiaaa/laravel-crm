# Requirements

Versions below were read from manifests and lockfiles on 2026-10-15. They are not a certification of every operating system or database release.

| Component | Declaration | Current locked requirement or version |
| --- | --- | --- |
| PHP | `^8.2` in Composer | **PHP 8.4 or newer within PHP 8.x** is required by locked Symfony 8 packages; PHP 8.2/8.3 cannot install this lockfile |
| Laravel | `^12.0` | 12.53.0 |
| Node.js | `>=20.0.0`; `.nvmrc` says `20` | Vite 7.3.1 and Laravel Vite plugin 2.1.0 require `^20.19.0 || >=22.12.0` |
| Composer | Composer project | Use Composer 2 and the committed lockfile |
| npm | `package-lock.json` v3 | Use `npm ci` with the committed lockfile |
| Frontend | Blade, Alpine, Tailwind, SIP.js | Alpine 3.15.8, Tailwind 4.2.0, SIP.js 0.21.2 |
| Queues/realtime | Horizon, Reverb | Horizon 5.45.0, Reverb 1.8.0 |
| Database | SQLite default example; MySQL configured | SQLite is used by tests; MySQL is the inspected application engine; a minimum production database version is not established by these files |

The dependency mismatch is documented rather than fixed because this change must not alter dependencies. Use a compatible installed PHP/Node runtime; do not use `composer update` to conceal it. Run `composer check-platform-reqs` after installation. Composer's `config.platform` pretends `ext-pcntl` and `ext-posix` exist; it does not install them.

## PHP capabilities

Laravel requires Ctype, Filter, Hash, Mbstring, OpenSSL, Session, and Tokenizer through its locked package. Install the PDO driver for your database. XML/DOM and PHPUnit's required extensions are needed for tests; Fileinfo and Zip are needed for relevant upload/archive workflows. Check every locked requirement with Composer rather than treating this list as exhaustive. Redis uses the configured client (`phpredis` in the example); that client needs its PHP extension.

Horizon requires PCNTL and POSIX. Native Windows PHP does not provide a complete Horizon runtime. Use Linux or WSL with those extensions for Horizon. Windows development can use database queues and separate processes; this does not verify Linux process signals and worker timeouts.

## Optional external components

- Redis for Horizon or Redis-backed queues/cache; database queues can serve isolated local work.
- VICIdial API/database, Asterisk AMI, SIP/WSS certificates and microphone access for telephony.
- Reverb and a WebSocket-capable reverse proxy for realtime broadcasts.
- SMTP and a separate email worker; Poppler `pdfinfo` for uploaded PDFs, other PDF/OCR tools when their import workflow is used. See [email campaigns](../EMAIL_CAMPAIGNS.md).
- A process manager and OS scheduler in production.

No capacity, simultaneous-user figure, or PBX/browser compatibility benchmark has been verified. Size deployments through representative staging tests.

Sources: [composer.json](../../composer.json), [composer.lock](../../composer.lock), [package.json](../../package.json), [package-lock.json](../../package-lock.json), [database config](../../config/database.php), [PHPUnit config](../../phpunit.xml). Next: [installation](installation.md).

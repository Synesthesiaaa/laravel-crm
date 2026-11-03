## Context

`http-bootstrap.js` sets `window.axios.defaults.timeout = 5000`. The import upload uses that shared instance without overriding its deadline. The reported text is the generic client fallback; it does not by itself prove a database-queue failure on the disconnected deployed server.

## Goals / Non-Goals

**Goals:** Let realistic uploads outlive the ordinary API deadline; clearly identify timeout versus HTTP 5xx feedback; verify actual jobs-table insertion and atomic failure handling.

**Non-goals:** Change shared telephony/API timeouts, queue connection, schema, roles, source retention, visual design, or remotely execute a real import.

## Decisions

- Override only the CSV POST timeout to 300,000ms. Keep polling and all other shared Axios requests at their existing deadlines; disabling timeouts entirely would leave stalled uploads indefinitely pending.
- For Axios timeout codes, advise refreshing recent imports before retrying because the server may already have accepted a request whose response was lost. For HTTP 5xx, show the status and a safe server-log recovery instruction, never raw exception content.
- Write regression tests first for an eight-second server response, safe messages, and real database-queue insertion without Queue::fake. Verify a missing jobs table rolls back import metadata and removes the private source.
- Reproduce the original five-second failure in Playwright using an isolated synthetic upload with an eight-second delayed response, then verify the rebuilt frontend accepts that same response and the real job exists.

## Risks / Trade-offs

- A longer client deadline cannot fix reverse-proxy limits, connectivity, absent migrations, or server errors. Preserve that distinction and do not claim the deployed server was inspected.
- A timed-out response does not imply that no job was inserted. Recovery instructions point to recent imports to avoid unnecessary duplicate previews.
- Deployment requires rebuilding/publishing the frontend assets and reloading the browser; a PHP-only deployment will retain the old timeout.

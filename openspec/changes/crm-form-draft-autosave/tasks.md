## Tasks

- [x] Extend the shared CRM form Alpine helper so it captures draft state, restores saved drafts, persists changes on input and navigation events, and submits asynchronously without a page reload.
- [x] Update the CRM form Blade partial to opt into the new autosave behavior, include inline feedback for asynchronous validation and save errors, and keep the non-JavaScript fallback markup intact.
- [x] Update the form submission controller to return JSON for asynchronous submissions while preserving the existing redirect flow for normal POST requests.
- [x] Add or update PHPUnit coverage for the shared form rendering hooks and the asynchronous success, validation failure, and fallback submission flows.

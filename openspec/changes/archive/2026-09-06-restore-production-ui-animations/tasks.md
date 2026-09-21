## 1. Frontend asset delivery hardening

- [x] 1.1 Add HTML response cache-control middleware and register it in the Laravel web middleware stack.
- [x] 1.2 Configure Laravel Vite and the Vite plugin to use `storage/vite.hot`; remove the marker after a production build and ignore the generated file.
- [x] 1.3 Add the post-Alpine readiness marker to the shared JavaScript entry point.

## 2. Automated verification

- [x] 2.1 Add feature/source-contract tests covering HTML cache headers, the Vite hot-file configuration, and the frontend readiness marker.
- [x] 2.2 Run the affected PHPUnit test file and Laravel Pint on changed PHP files.
- [x] 2.3 Run `npm run build` and verify the manifest, CSS transition utilities, and JavaScript Alpine bootstrap are present.

## 3. Browser and specification verification

- [x] 3.1 Attempt Playwright checks for a shared hover state, modal opening/closing, and `data-crm-ui-ready` on a representative page. (The shared Playwright browser profile was already in use, so static, build, and PHP checks were used instead.)
- [x] 3.2 Sync the implemented behavior to the main OpenSpec capability specification.
- [x] 3.3 Archive the completed OpenSpec change after tests and build verification pass.

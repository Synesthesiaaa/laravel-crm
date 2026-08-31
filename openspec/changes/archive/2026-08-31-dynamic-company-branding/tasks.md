## 1. Branding foundation

- [x] 1.1 Add branding configuration defaults, storage disk/path settings, and the cached `BrandingService` backed by `system_settings`.
- [x] 1.2 Add the shared `x-brand` Blade component and global view-composer data so guest and authenticated views resolve the same branding source.

## 2. Super Admin management

- [x] 2.1 Add `UpdateBrandingRequest` with Super Admin authorization and secure raster-image/company-name validation.
- [x] 2.2 Add BrandingService persistence with generated filenames, safe upload-before-update behavior, old custom asset cleanup, cache invalidation, and activity logging.
- [x] 2.3 Add the configuration controller action, protected route, and responsive Branding tab/form with previews, inline errors, and existing status feedback.

## 3. Application integration

- [x] 3.1 Integrate dynamic page-title suffixes and versioned favicon links into authenticated and guest/pending-login layouts.
- [x] 3.2 Replace sidebar static campaign/icon branding with the shared company brand while preserving campaign context and collapsed/mobile behavior.
- [x] 3.3 Add company identity to the dashboard welcome hierarchy without changing dashboard business logic.

## 4. Automated verification

- [x] 4.1 Add service tests for defaults, cache reuse/invalidation, missing assets, generated paths, cleanup, and storage failure handling.
- [x] 4.2 Add feature tests for Super Admin authorization, validation, persistence, file upload/replacement, and branding rendering on login/sidebar/dashboard/title/favicon.
- [x] 4.3 Run Pint, affected PHPUnit tests, and the frontend build; fix regressions found by those checks.
- [x] 4.4 Run Playwright browser validation for the settings flow and representative login/dashboard/sidebar viewport states, including console/network health.

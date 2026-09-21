## Why

The CRM currently relies on static application identity values, so a customer-facing company name, logo, and browser icon cannot be changed by an authorized administrator without editing source code. A centralized branding capability will let Super Admins manage the CRM identity once and have it reflected consistently across authenticated and unauthenticated experiences.

## What Changes

- Add a persistent branding settings capability for company name, logo, and favicon.
- Add a Super Admin-only settings screen with previews, validation feedback, and safe replacement/reset behavior.
- Resolve branding through one cached service/source with defaults and missing-asset fallbacks.
- Apply the resolved branding to browser titles, favicon links, login, authenticated shell/sidebar, and dashboard identity.
- Store uploaded assets through Laravel's configured filesystem with secure raster-image validation and cache-busting URLs.
- Add authorization, cache invalidation, upload cleanup, rendering, and fallback regression coverage.
- Preserve the existing application palette and CRM business logic.

## Capabilities

### New Capabilities

- `company-branding`: Manage, cache, secure, and render customer-facing company identity across the CRM.

### Modified Capabilities

<!-- No existing OpenSpec capability currently owns application branding requirements. -->

## Impact

- Laravel settings/data layer, migration, service/provider, authorization, request validation, controller, routes, and tests.
- Guest/authenticated Blade layouts, login, sidebar, dashboard, and page-title/favicon integration.
- Public/storage asset delivery and the existing Tailwind/Blade UI patterns.
- No new dependency is required; existing roles, filesystem, caching, and notification conventions will be reused.

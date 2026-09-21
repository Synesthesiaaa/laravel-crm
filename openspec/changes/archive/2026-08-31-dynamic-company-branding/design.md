## Context

The application already has a generic `system_settings` table/model, a Super Admin-only `admin.configuration` route, a shared authenticated Blade shell, a separate guest login view, and a public filesystem disk. Branding is currently split between `config('app.name')`, a static favicon, a signal icon in the sidebar, and campaign names shown in the shell. The change must preserve the existing palette, roles, telephony behavior, and campaign-specific identity while adding a customer-facing company identity.

## Goals / Non-Goals

**Goals:**

- Provide one cached, fallback-safe source for company name, logo, and favicon.
- Let only Super Admins update those values from the existing configuration area.
- Store validated raster assets on Laravel's public filesystem with generated names and safe replacement cleanup.
- Apply the source to page titles, favicon links, login/pending-login pages, sidebar, and dashboard.
- Keep the settings screen responsive, accessible, and consistent with existing Blade/Tailwind patterns.

**Non-Goals:**

- No theme/color editor, email/PDF branding, company contact data, or campaign branding changes.
- No SVG upload support or new third-party dependency.
- No replacement of the existing `APP_NAME` technical configuration or campaign/session naming.
- No reset workflow unless a later change defines a clear product requirement for it.

## Decisions

### Reuse `system_settings` instead of adding a branding table

Use three namespaced keys: `branding_company_name`, `branding_logo_path`, and `branding_favicon_path`. The existing unique key/value model already supports application settings and activity logging, so a migration would add structure without improving the contract. The service owns the keys and defaults so callers do not duplicate them.

### Centralize resolution in `BrandingService`

Bind the service through Laravel's container and expose a `resolve()`/`update()` API returning normalized branding data: name, custom paths, public URLs, versioned URLs, and alt text. Cache the normalized result under a dedicated key with a short TTL; `update()` forgets the key after persistence and before the redirect. A missing setting or missing stored file returns the configured application name (then `CRM`) and the built-in signal/favicon fallbacks.

### Use the configured public filesystem disk with generated paths

Branding assets are stored under `branding/` on a configurable branding disk that defaults to Laravel's existing `public` disk. The service uses `Storage::disk(...)->url()` rather than hard-coded filesystem paths, keeping the design compatible with an object-storage disk later. UUID-based filenames provide cache busting and avoid trusting original filenames. SVG is excluded because no trusted sanitizer is present.

### Upload before persistence, then clean up old custom assets

The update flow validates the request, stores new files first, persists all setting values in a database transaction, invalidates the cache, then deletes only old paths inside the branding directory. If storage or persistence fails, newly stored files are removed and the previous settings remain intact. Built-in fallback assets are never targeted for deletion.

### Share branding through a view composer and render it with one Blade component

Register a global view composer that resolves branding lazily through the cached service, allowing both guest and authenticated templates to consume the same data without `auth()` assumptions. A reusable `x-brand` component owns logo-vs-fallback rendering, sizing, `object-contain`, and meaningful alt text. Layouts use the service data for title suffixes and favicon links; the sidebar component keeps campaign context separate from company branding.

### Extend the existing Configuration tab

Add a `Branding` tab to `admin.configuration` and a dedicated POST route under the already protected Super Admin route group. Use a Form Request for authorization and validation, multipart form encoding, field-level errors, an error summary when needed, previews, and the existing session status/toast feedback. Keep the current general, telephony, diagnostics, and retention tabs unchanged.

## Risks / Trade-offs

- [Existing deployments may not have `storage:link`] → use Laravel's public disk URL convention, document the operational prerequisite in the implementation/tests, and keep the UI fallback-safe when a custom asset cannot be loaded.
- [Remote disks may not support cheap existence checks] → resolve and cache the result once per request/cache period; treat an unavailable custom asset as a fallback rather than emitting a broken URL.
- [Image files can be renamed or deleted outside the panel] → verify existence during resolution and never let a missing custom path break page rendering.
- [A very long company name can compress the shell] → retain the full stored value, allow wrapping in settings/login, and use truncation only in constrained sidebar presentation with the full name available via `title`/accessible text.
- [Browser favicon caching is aggressive] → generated filenames and a content/path version query parameter make normal navigation pick up a replacement.

## Migration Plan

No database migration is required because `system_settings` already exists and accepts namespaced keys. Deploy the service, config, controller/request, component, view updates, and tests; run the existing migration state unchanged. Rollback is a code rollback; previously uploaded branding files may remain harmlessly under `storage/app/public/branding/` and can be removed through a reviewed filesystem cleanup if needed.

## Open Questions

- The existing deployment must continue to provision the public storage link when using the local public disk; this is an operational prerequisite, not a user-facing behavior change.

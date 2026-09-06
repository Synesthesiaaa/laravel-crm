## Context

The application uses Laravel's `@vite` directive to load one shared CSS entry point and one shared JavaScript entry point. The JavaScript bundle imports Alpine.js, registers the transition/focus plugins, starts Alpine, and owns the soft-navigation rehydration layer. Vite emits hashed files under `public/build/assets` and the Nginx deployment guidance already treats those files as immutable.

The browser log captured during investigation referenced an older hashed JavaScript file than the current build. That is consistent with cached HTML or a stale Vite hot-file marker: the shell can point at a previous or unavailable bundle while the server still serves the page. When the shared bundle is unavailable, inline `x-show`, `x-transition`, hover utilities, and modal triggers appear to stop working together.

## Goals / Non-Goals

**Goals:**

- Make the HTML shell revalidate so it always resolves the current Vite manifest and hashed asset names.
- Isolate the Vite development hot-file marker from the public build directory and remove stale hot markers during production builds.
- Expose a small client-side readiness marker after Alpine and soft navigation are initialized, making future production smoke checks unambiguous.
- Preserve the existing motion design, reduced-motion behavior, modal semantics, and soft-navigation behavior.

**Non-Goals:**

- Redesigning animations or changing transition durations/easing.
- Adding a frontend framework, service worker, CDN, or new dependency.
- Changing API, database, authentication, or telephony behavior.

## Decisions

### 1. Revalidate HTML, cache hashed assets

Add a web middleware that applies `private, no-cache, no-store, must-revalidate` to successful HTML responses. This prevents a browser/proxy from retaining an old Blade shell that references deleted Vite hashes. Static hashed `/build/` files remain responsible for their long-lived cache policy at the web server.

Alternative considered: append a query string to every asset URL. Laravel/Vite already provides content hashes, so changing URLs manually would duplicate the manifest's responsibility and would not prevent stale HTML from being served.

### 2. Move the Vite hot file out of `public/`

Configure both the Laravel Vite facade and the Vite plugin to use `storage/vite.hot`. This prevents an old `public/hot` file from making production `@vite` output point at a development server. The production build closes by removing the storage hot file, while local `npm run dev` continues to use the same configured path.

Alternative considered: rely on operators to delete `public/hot` manually. That is easy to miss during a deployment and is the failure mode this change is intended to eliminate.

### 3. Mark the frontend runtime ready after initialization

After registering Alpine stores/plugins and calling `Alpine.start()`, set `document.documentElement.dataset.crmUiReady` and expose `window.crmUiRuntime`. This is diagnostic state only; it does not replace Alpine transitions or make hidden controls visible when the bundle has failed to load.

Alternative considered: add an inline fallback that reveals hidden controls when JavaScript fails. That would expose menus/modals in an unusable state and weaken the application's interaction contract.

## Risks / Trade-offs

- [Risk] Users with an intentionally offline cached shell will revalidate HTML on the next request. → Mitigation: only the HTML shell is revalidated; versioned static assets remain cacheable.
- [Risk] A stale `storage/vite.hot` file could still point to a development server before the next build. → Mitigation: the configured build removes it, and the production deployment step must run `npm run build` before serving the new release.
- [Risk] The readiness marker could be mistaken for a complete browser health check. → Mitigation: name it explicitly as a runtime marker and keep automated checks for asset/build delivery separate.

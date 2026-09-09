## MODIFIED Requirements

### Requirement: Production HTML resolves the current frontend build

The application SHALL prevent successful HTML shell responses from being served from a stale browser or intermediary cache, while allowing hashed Vite build assets to use long-lived caching. Route-specific Vite entries SHALL resolve from the same current manifest as the shared shell.

#### Scenario: Authenticated page loads after a frontend deployment

- **WHEN** an authenticated user requests a page rendered with the shared application layout
- **THEN** the HTML response instructs browsers and intermediaries to revalidate instead of reusing an older shell that may reference obsolete hashed assets

#### Scenario: Versioned frontend assets are requested

- **WHEN** the browser requests a hashed CSS or JavaScript file from the Vite build output, including a route-specific dashboard or embedded-form entry
- **THEN** the asset URL remains versioned by the Vite manifest and is eligible for the existing immutable static-asset cache policy

### Requirement: Production does not accidentally use a development Vite server

The application SHALL use a non-public Vite hot-file location and SHALL remove the configured hot file after a production build so `@vite` resolves the production manifest when no dev server is running.

#### Scenario: Production build completes

- **WHEN** the production Vite build finishes
- **THEN** the configured hot-file marker is absent and the Laravel layout resolves shared and route-specific assets from `public/build/manifest.json`

#### Scenario: Local Vite development runs

- **WHEN** a developer runs the Vite development server
- **THEN** the same configured hot-file location is used and `@vite` can still load the dev server assets

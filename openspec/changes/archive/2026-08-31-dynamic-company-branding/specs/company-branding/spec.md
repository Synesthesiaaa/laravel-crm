## ADDED Requirements

### Requirement: Centralized branding settings
The system SHALL resolve the customer-facing company name, logo, and favicon through one branding service backed by namespaced system settings and safe defaults.

#### Scenario: Defaults are used before configuration
- **WHEN** no branding settings exist
- **THEN** the service returns the configured application name when present, otherwise `CRM`, uses the built-in signal mark as the logo fallback, and uses the built-in favicon as the favicon fallback

#### Scenario: Configured values are returned
- **WHEN** branding settings contain a company name and valid stored asset paths
- **THEN** the service returns the stored company name and public URLs for both configured assets

#### Scenario: Missing custom assets fall back safely
- **WHEN** a stored custom logo or favicon path no longer exists
- **THEN** the service returns the corresponding built-in fallback URL/mark and never exposes a broken image URL

### Requirement: Cached branding resolution
The system SHALL cache normalized branding data and SHALL invalidate that cache immediately after a successful branding update.

#### Scenario: Repeated reads use the branding cache
- **WHEN** multiple views resolve branding during a request or cache lifetime
- **THEN** the database-backed settings are read through the dedicated branding cache rather than queried independently by each view

#### Scenario: A successful update is visible on the next request
- **WHEN** a Super Admin saves a valid branding update
- **THEN** the branding cache is forgotten and the next request resolves the new name and asset URLs without manual cache clearing

### Requirement: Super Admin branding management
The system SHALL provide a Branding tab in the existing Super Admin configuration area where an authorized Super Admin can edit the company name and upload a logo and favicon.

#### Scenario: Super Admin views branding settings
- **WHEN** an authenticated Super Admin opens the Branding tab
- **THEN** the form displays the current company name, current logo/fallback preview, current favicon/fallback preview, helper text, and a save action

#### Scenario: Non-Super Admin cannot update branding
- **WHEN** an Agent, Team Leader, or Admin submits the branding update endpoint
- **THEN** the request is rejected with the existing authorization response and no branding setting or asset changes

#### Scenario: Unauthenticated users cannot access management
- **WHEN** a guest requests the Branding tab or update endpoint
- **THEN** the request is rejected by authentication/authorization middleware and no branding data is changed

### Requirement: Branding input validation and safe storage
The system SHALL validate company names and uploaded branding files before persistence, store accepted files with generated names on the configured public branding disk, and support raster formats only.

#### Scenario: Valid branding input is accepted
- **WHEN** a Super Admin submits a non-empty company name and valid PNG, JPEG, or WebP logo/favicon files within the configured size limits
- **THEN** the files are stored below the branding directory with generated safe filenames and the settings point to those paths

#### Scenario: Invalid company name is rejected
- **WHEN** the company name is empty, non-string, or longer than the configured maximum
- **THEN** the update is rejected with an inline company-name validation error and existing branding remains unchanged

#### Scenario: Unsafe or oversized files are rejected
- **WHEN** an upload is an unsupported MIME/type, SVG, executable/script file, or exceeds its size limit
- **THEN** the update is rejected with a field-specific validation error and no broken path is persisted

#### Scenario: Replacing a custom asset cleans up the old asset
- **WHEN** a new logo or favicon is successfully persisted for a setting that already references a custom branding path
- **THEN** the setting points to the new generated path and the old feature-owned custom file is removed while built-in fallback assets remain untouched

#### Scenario: Storage failure preserves existing branding
- **WHEN** storing a new branding file fails before the settings transaction completes
- **THEN** newly created replacement files are cleaned up where possible and the previous settings remain active

### Requirement: Shared branding rendering
The system SHALL apply the centralized branding source to browser identity and the main guest/authenticated CRM experiences without changing campaign or technical application configuration behavior.

#### Scenario: Authenticated page title and favicon use branding
- **WHEN** an authenticated layout page is rendered
- **THEN** its title includes the page title and configured company name, and its favicon link points to the configured favicon with cache-busting or generated-versioned URL behavior

#### Scenario: Login pages use branding without authentication
- **WHEN** the login or pending-login page is rendered for a guest
- **THEN** the page displays the company name and logo and uses the same favicon source without requiring an authenticated user

#### Scenario: Sidebar uses company branding
- **WHEN** the authenticated sidebar is rendered in expanded, collapsed, or mobile mode
- **THEN** it displays the configured logo or safe fallback mark and company name, preserves the logo aspect ratio, and keeps campaign context separate from the company brand

#### Scenario: Dashboard uses company branding naturally
- **WHEN** the dashboard welcome area is rendered
- **THEN** the company identity is visible in the existing hierarchy without duplicating a large logo unnecessarily

#### Scenario: Branding remains accessible and responsive
- **WHEN** a user navigates the settings, login, sidebar, or dashboard at desktop, tablet, or mobile widths
- **THEN** meaningful logo images have descriptive alt text, fields have visible labels and connected errors, controls retain visible keyboard focus, and long company names do not create horizontal overflow or break the shell

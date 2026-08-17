# Agent Screen Access Toggle Design

## Goal

Allow a Super Admin to turn Agent Screen access on or off globally. Agent Screen functionality is hidden and unavailable by default, while the existing Super Admin configuration area remains available so the feature can be enabled again.

## Scope

This change covers the shared Agent Screen entry points and the Agent Capture webform endpoints used by VICIdial. It does not remove Agent Screen field configuration data, alter regular CRM form fields, or change the existing Super Admin-only authorization for Agent Screen configuration.

## Behavior

- The new `agent_screen_access` feature flag is stored in the existing `system_settings` table through `TelephonyFeatureService`.
- The flag defaults to disabled when no setting has been saved.
- Super Admins can change the flag from Configuration → Telephony Features.
- When the flag is disabled for non-Super Admin users:
  - The main Agent Screen item is omitted from the Telephony sidebar.
  - Agent Screen links returned by global search are omitted.
  - Direct requests to the Agent Screen page are rejected.
  - Agent Capture webform pages and capture submissions are rejected.
- When the flag is enabled, the existing Agent Screen navigation and endpoints remain available.
- Super Admins retain access to the existing configuration page and bypass the feature gate, following the current telephony feature-gating convention.
- Regular CRM forms remain available and are not changed by this toggle.

## Implementation

`TelephonyFeatureService` will add the new feature key while preserving the current enabled-by-default behavior for existing telephony features. The new key will use a disabled default and participate in the existing cache invalidation and configuration activity logging.

`ConfigurationController` will continue using the existing bulk update endpoint. The Telephony Features tab will add a labeled checkbox for Agent Screen access and explain that the setting controls the Agent Screen and Agent Capture surfaces.

The web route for the Agent Screen page, the Agent Capture webform page, and the Agent Capture API submission route will use the existing `telephony_feature` middleware. The middleware will support an HTML response suitable for browser requests while retaining the current JSON response for API requests.

Blade navigation and global search will read the same feature service so disabled links are not rendered. The Super Admin configuration link remains available through the existing Super Admin navigation and dashboard.

## Testing

- The feature service returns disabled for the new key when no setting exists, persists changes, and flushes its cache.
- The Super Admin configuration form displays the new option and saves enabled and disabled values.
- A regular user does not receive the Agent Screen sidebar or global-search link when disabled.
- A regular user receives those links and can load the Agent Screen page when enabled.
- A regular user receives an access-denied response for direct Agent Screen and Agent Capture requests when disabled.
- Super Admin access to configuration remains available while the feature is disabled.
- Existing telephony feature behavior and regular CRM forms remain unchanged.

# Quick Form Widget Form Selector Design

## Context

The persistent Quick Form widget currently receives the active campaign and, on
form pages, the current form URL. Users can change forms through the main
application navigation, but the widget does not provide a direct form selector.
That makes switching forms in the Vicidial + Quick Form split workspace slower
and can unnecessarily involve the outer page lifecycle.

## Decision

Add an API-driven form selector inside the Quick Form widget header. The
existing authenticated `/api/forms/quick/bootstrap` endpoint will return the
active forms for the current campaign. The widget will use that metadata to
render a compact selector and will update only its embedded iframe when a form
changes.

The selector will list only active forms configured for the current campaign,
with the current form selected by default. The selected form will not become a
new persisted user preference; it remains the widget's current session state.

## Architecture and data flow

1. `QuickFormController::bootstrap` returns the existing first-form bootstrap
   fields plus a normalized list of active form types and labels.
2. `quick-form-widget.js` loads the metadata during initialization, even when
   the widget already has a form URL from the current page.
3. The Quick Form header renders a labeled selector from the loaded options.
4. A valid selection calls the existing iframe URL synchronization path with
   the active campaign and selected form type. The resulting URL includes
   `widget_embed=1` and a cache-busting frame key.
5. The outer page, Vicidial widget, Vicidial iframe, and workspace split state
   remain mounted and unchanged.

## Error handling

- If form metadata cannot be loaded, the current Quick Form iframe remains
  usable and the selector is disabled or omitted.
- If a client-side selection is not present in the loaded active-form list, it
  is ignored.
- The selector does not change the active campaign and does not expose forms
  from another campaign.

## UI behavior

- The selector appears in the Quick Form header in normal and split layouts.
- Its label and control remain usable at the desktop split breakpoint.
- Changing the selection opens the new form in the existing Quick Form panel
  without navigating the parent page.
- Existing drag, resize, minimize, split, and Exit split controls remain
  available.

## Testing

- Extend the bootstrap API test to assert the active-form option list.
- Add JavaScript tests for option normalization and selection guards.
- Add view/feature coverage for the selector wiring and selected form state.
- Use Playwright to select a different form while split view is active and
  verify the Quick Form iframe changes while the Vicidial root and split state
  remain present.

## Non-goals

- Persisting a user's last selected Quick Form.
- Allowing forms from another campaign.
- Changing Vicidial session behavior or the outer soft-navigation lifecycle.

# Clarify the login page

## Problem

The distilled login page is visually focused, but a few messages still assume users already understand the CRM entry flow. The heading uses the broad term “workspace,” the campaign selector does not explain its effect, the theme control begins with a generic accessible name, and the invalid-credential message does not tell a user how to recover.

## Goal

Make the login task self-explanatory for call-center agents and supervisors who may be signing in under time pressure: identify the destination, explain the campaign choice, name the theme action precisely, and provide a privacy-safe recovery instruction after failed credentials.

## Scope

- Clarify the login heading and supporting copy.
- Explain that the selected campaign becomes the user's starting work area after sign-in.
- Give the theme toggle a specific initial accessible name and title.
- Make invalid-credential feedback actionable without revealing which credential was wrong.
- Preserve all routes, request fields, CSRF protection, campaign values, branding, themes, focus states, and responsive layout.

## Non-goals

- No changes to authentication rules, rate limiting, session handling, or campaign authorization.
- No new account recovery flow or password reset behavior.
- No new product claims or marketing content.

## Audience and emotional context

The primary audience is a call-center agent starting a shift or returning to a queue. The user may be moving quickly or may already be frustrated by a failed sign-in, so the interface should use plain, direct language and make the next action obvious without adding noise.

## Validation

- Update the focused login feature assertions for the clarified copy and error message.
- Run the focused PHPUnit test, Pint, and the configured Vite build.
- Use Playwright to verify the normal, campaign, theme, keyboard-focus, and invalid-credential states at mobile and desktop widths.

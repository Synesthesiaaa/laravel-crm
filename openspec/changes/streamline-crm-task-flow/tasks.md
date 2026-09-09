## 1. Shared UI primitives

- [x] 1.1 Add the reusable `x-empty-state` Blade component with token-based tones, accessible title/description structure, and optional action.
- [x] 1.2 Add shared CSS for empty states, Agent tool navigation, and collapsible sidebar group controls with responsive focus treatment.

## 2. Focused Agent workspace

- [x] 2.1 Add Alpine `activeTool` state and feature-aware tool selection helpers to the Agent Screen.
- [x] 2.2 Replace the simultaneous advanced tool stack with an accessible tablist and one visible tool panel at a time.
- [x] 2.3 Make the transfer shortcut select the transfer tool and scroll the navigator into view.
- [x] 2.4 Apply shared empty-state guidance to missing Agent Screen fields and recent activity.

## 3. Role-aware navigation

- [x] 3.1 Add route-aware expanded defaults and accessible collapsible group toggles to the shared sidebar.
- [x] 3.2 Preserve role/feature route visibility, mobile drawer behavior, active-route styling, and soft-navigation compatibility.
- [x] 3.3 Add grouped labels and clearer terminology where the sidebar currently uses abbreviations.

## 4. Data and dashboard states

- [x] 4.1 Add compact empty/unavailable fallbacks to dashboard activity chart containers and avoid rendering blank graph frames for zero-value series.
- [x] 4.2 Apply shared state language to dashboard activity, leaderboard, and campaign report empty states.
- [x] 4.3 Apply consistent dynamic empty/unavailable state presentation to major report charts and tables without changing API contracts.
- [x] 4.4 Give the admin dashboard an explicit no-activity state when chart data is absent instead of silently omitting the chart region.

## 5. Automated verification

- [x] 5.1 Add or update PHPUnit feature assertions for Agent disclosure, shortcut behavior, sidebar group state, and empty-state rendering.
- [x] 5.2 Run focused PHPUnit tests and Laravel Pint for modified PHP files.
- [x] 5.3 Run the configured frontend build and relevant JavaScript checks.

## 6. Browser and specification closeout

- [x] 6.1 Run Playwright checks at 375px, 768px, 1024px, and 1440px for Agent Screen, navigation, and representative empty states.
- [x] 6.2 Check browser console errors, keyboard focus order, no horizontal overflow, and tab/tabpanel behavior. The local telephony setup still reports a SIP credentials 422.
- [x] 6.3 Sync the delta specs to canonical OpenSpec files after implementation.
- [ ] 6.4 Run final OpenSpec verification and archive only after all required validation is complete.

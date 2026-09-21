## Context

The telephony reports page currently sits behind the higher-role route guard, but it still presents report data as raw VICIdial text in tabbed partials. The existing `ReportingController` and `ReportingService` already expose the report payloads, and `VicidialNonAgentApiService` already normalizes the raw VICIdial response into `raw_response` and parsed `rows`. The UI work is therefore mostly a view-layer refactor that should reuse the current API contracts instead of introducing a new reporting backend.

The app already uses Alpine, ApexCharts, and a reusable chart lifecycle pattern (`window.crmCharts`) in dashboard and supervisor views. That gives us a stable implementation path for summary cards, charts, and tables without adding new dependencies.

## Goals / Non-Goals

**Goals:**
- Present telephony reporting as a readable dashboard instead of a raw VICIdial dump.
- Surface call status stats, agent performance, and disposition data in cards, charts, and tables.
- Keep raw VICIdial output available in a collapsed debug area for troubleshooting.
- Preserve the existing report filters and refresh flow.
- Reuse the app's current chart loading and soft-nav lifecycle patterns.

**Non-Goals:**
- No new database tables or persistence layer.
- No new reporting API endpoints.
- No change to the existing higher-role access control on the reports route.
- No attempt to redesign unrelated telephony screens.

## Decisions

1. Keep the existing reporting API contract and transform the payloads in the view layer.
   - Rationale: the backend already returns the data needed for the dashboard, including raw text and parsed rows. Keeping the transformation near the UI minimizes backend churn and makes the page easier to iterate on.
   - Alternatives considered:
     - Add a dedicated server-side presenter/controller view model. Rejected because it adds more backend surface area for a UI-focused change.
     - Add a new dashboard API endpoint. Rejected because the current endpoints already support the necessary refresh flow.

2. Replace the tabbed raw-output layout with a single dashboard-first composition.
   - Rationale: higher roles need at-a-glance context. A single page with visible KPI cards, charts, and tables is easier to scan than tabs that hide the main metrics.
   - Alternatives considered:
     - Keep tabs and improve each tab visually. Rejected because the page would still feel segmented and raw-output driven.
     - Split the reports into separate pages. Rejected because the current page already groups the telephony reports in one place and the filters apply across them.

3. Use one collapsed debug and utility area at the bottom of the page.
   - Rationale: the raw VICIdial payload is still valuable when reports look wrong, but it should not dominate the default experience. A collapsed area preserves troubleshooting without sacrificing readability.
   - Alternatives considered:
     - Inline debug output under every report section. Rejected because it creates visual noise.
     - Remove debug output entirely. Rejected because the raw VICIdial response is sometimes the only practical way to diagnose parsing or upstream API issues.

4. Reuse the existing ApexCharts loader and chart registry.
   - Rationale: the application already uses dynamic ApexCharts loading plus chart group cleanup across soft navigation. Reusing that pattern reduces regression risk and keeps the reports page consistent with other dashboards.
   - Alternatives considered:
     - Introduce a new chart library. Rejected because it adds no value for this feature.
     - Render static SVG or canvas charts by hand. Rejected because it would be more code and less maintainable.

5. Preserve the existing recording lookup as a collapsed utility rather than a visible tab.
   - Rationale: it is still a useful report helper, but it should sit beside the debug output instead of competing with the main dashboard content.
   - Alternatives considered:
     - Remove the recording lookup from the page. Rejected because it would drop a working tool.
     - Keep it as a top-level tab. Rejected because that continues the tabbed/raw-output pattern this change is meant to replace.

6. Treat configured Vicidial system dispositions as report-excluded data and expose a panel-wide scope filter.
   - Rationale: higher roles need one control that applies across the full reports panel, and the CRM should not count system-generated dispositions the same way it counts agent dispositions.
   - Alternatives considered:
     - Hard-code a fixed list of excluded codes in the view. Rejected because each Vicidial deployment can label system dispositions differently.
     - Hide the system filter inside the disposition section only. Rejected because the request is for a panel-level control.

## Risks / Trade-offs

- [Risk] VICIdial report rows may vary by campaign or version, which can make parsing brittle. → Mitigation: build defensive normalizers that fall back to table-only rendering and keep the raw debug section available.
- [Risk] A client-side dashboard depends on the report payloads arriving in a consistent shape. → Mitigation: show empty states and load failures explicitly instead of silently hiding panels.
- [Risk] The page may feel dense on smaller screens. → Mitigation: use a stacked mobile layout, collapse the debug area by default, and keep each dashboard section self-contained.
- [Risk] Three report requests can slow the initial load. → Mitigation: continue loading the requests in parallel and show a focused loading state for each section or the overall page.

## Migration Plan

This change does not require a database migration.

Deployment steps:
1. Ship the Blade and JavaScript view changes.
2. Validate the reports page in a browser with a higher-role account.
3. Confirm the dashboard sections render for successful payloads and the debug area stays collapsed by default.

Rollback strategy:
- Restore the previous `resources/views/reports/*` implementation if parsing or rendering regressions appear.
- The API endpoints remain unchanged, so rollback should not require any backend data repair.

## Open Questions

- Should the recording lookup remain permanently in the collapsed utility area, or should it move to a separate telephony tools page in a later change?

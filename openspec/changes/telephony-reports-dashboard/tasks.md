## 1. Build the dashboard shell
- [x] Replace the tabbed raw-output layout in `resources/views/reports/index.blade.php` with a dashboard-first page structure.
- [x] Add summary KPI cards, section headers, and responsive layout containers for call status, agent performance, and disposition reporting.

## 2. Normalize report payloads
- [x] Add client-side parsing helpers for `call_status_stats`, `agent_stats_export`, and `call_dispo_report` payloads.
- [x] Map the parsed report rows into chart series, table rows, and summary values with defensive fallbacks when VICIdial output is incomplete.

## 3. Add charts and structured tables
- [x] Render dashboard charts with the existing ApexCharts loader and `window.crmCharts` lifecycle helpers.
- [x] Replace the raw `<pre>` output in the report partials with readable tables and empty/error states.
- [x] Keep the recording lookup available as a collapsed utility rather than a primary tab.

## 4. Add the collapsible debug area
- [x] Create a collapsed debug/diagnostics section that shows raw VICIdial output, parsed rows, and request context.
- [x] Ensure the debug section is closed by default and expands cleanly without affecting the dashboard sections.

## 5. Verify the change
- [x] Add or update feature tests for the reports page rendering and access expectations.
- [x] Run the focused PHPUnit test(s) for the reports page.
- [x] Verify the UI in a browser and confirm the dashboard loads, charts render, and the debug section stays collapsed until opened.

## 6. Add disposition exception filtering
- [x] Add a panel-wide disposition scope control to the reports filter bar.
- [x] Exclude configured Vicidial system dispositions from CRM report persistence and dashboard aggregation.
- [x] Add or update tests that cover the report filter and system disposition exclusion path.

## Why

The current telephony reports page exposes VICIdial output as raw text, which is difficult for team leaders and admins to read quickly. This change turns that page into a dashboard-style report so higher roles can review call status, agent performance, and disposition trends without digging through unstructured output.

## What Changes

- Replace the current raw-text-first reporting experience with a dashboard layout built around summary cards, charts, and tables.
- Show call status metrics in a visual summary with readable totals and trend breakdowns.
- Show agent performance in a tabular and charted format that is easier to scan than VICIdial export text.
- Show disposition reporting in a structured table and chart view.
- Keep the raw VICIdial response available in a collapsed debug section for troubleshooting.
- Preserve the existing higher-role access restriction on the reports page.

## Capabilities

### New Capabilities
- `telephony-reports-dashboard`: Dashboard-style telephony reports with call status stats, agent performance, disposition summaries, and a collapsible debug view.

### Modified Capabilities
- None

## Impact

- `resources/views/reports/index.blade.php` and report partials will change substantially.
- `app/Http/Controllers/ReportsController.php` may need small view-model adjustments if the dashboard needs precomputed defaults.
- Existing report API endpoints under `app/Http/Controllers/Api/ReportingController.php` and `app/Services/Telephony/ReportingService.php` will continue to supply the underlying data.
- Frontend chart rendering will continue to use the existing ApexCharts and soft-nav lifecycle patterns already used elsewhere in the app.

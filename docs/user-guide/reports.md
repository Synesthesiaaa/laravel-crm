# Read dashboards and reports

**Dashboard** summarizes configured campaign business activity. **Administration → Reports** opens **Telephony Reports** for Team Leaders, Admins, and Super Admins. **Operations Insights** is a separate reporting surface described in the [supervisor and operations guide](../admin/supervisor-and-operations.md).

## Choose the scope before reading a number

1. Choose the **CRM Campaign**.
2. Choose **Report Mode**: **Historical**, **Live**, or **Today**.
3. For historical reporting, choose **Start Date** and **End Date**.
4. Open **More Filters** when you need **VICIdial Campaigns**, **Disposition Scope**, or **Comparison**.
5. Select **Refresh Reports**. Review the displayed campaign, period, source health, and freshness before comparing results.

Historical reports concern the selected period. Today means midnight to now in the report's effective timezone. Live reports use an operational window and current sources; they should not be treated as an all-day ledger. Mapped VICIdial campaigns and the server's reporting permissions affect what can be returned.

## What the historical metrics mean

| Metric | Implemented meaning |
| --- | --- |
| Total calls | Available scoped call-status totals, subject to selected disposition scope. |
| Answer rate | Verified answered customer attempts ÷ valid customer attempts × 100. Verified human and machine answers count; abandoned rate is not subtracted. |
| Abandoned rate | Verified live answers disconnected before agent connection ÷ all verified live answers × 100. Displayed beside Contact Rate. A disposition code alone cannot establish abandonment. |
| Contact rate | Unique dialer leads with a verified live agent–customer conversation ÷ unique attempted dialer leads × 100. Redials count once per lead. |
| Calls per agent | Total calls ÷ agents with activity, rounded to two decimals; unavailable when no usable agent count exists. |
| Average talk time | The parsed agent summary uses total talk seconds ÷ total agent calls when talk data is available, rounded to one decimal. It is not assumed to use only answered calls. |

Standardized rates share one verified evidence service across the dashboard, campaign monitoring, historical reports, Live/Today and CSV exports. Each percentage uses two decimals; hover its label for its formula and verified counts or the reason for N/A. Complete empty evidence returns 0.00%; missing evidence is N/A. Raw VICIdial counts, status tables and the optional configured-disposition funnel are diagnostic views, not substitutes for these rate inputs. Disposition filters apply to raw reports, not standardized lifecycle metrics. The Agent login filter uses verified participants; agent-filtered abandonment is N/A because pre-agent calls cannot be assigned reliably.

Use **Export verified metrics CSV** to download the same calculations with counts, scope, formula and provenance. Live rates disclose their source watermark; feeds more than 60 seconds behind become unavailable. Production activation requires a verified evidence feed; existing disposition records do not populate it automatically. See [verified metric operations](../telephony/verified-call-metrics.md) for source verification, import/webhook setup and coverage requirements.

Dashboard **Daily Comparison** has an **Overall / form** selector. Its chart and accessible data table compare the selected form against the equivalent previous-month days, with the existing volume/amount controls. Daily, weekly, and monthly activity charts show an Overall series and a named series for each form; use the legend to show or hide a series. Empty forms retain zero buckets. Activity counts all submissions; Daily Comparison follows configured sales qualification rules.

## Sales are a separate measure

Dashboard sales come from configured CRM form records, not simply from every answered call or a universal SALE code. In legacy field mode, a record counts when at least one field marked as a sale amount contains a numeric value; numeric zero still qualifies, and the marked numeric values are summed. Custom form rules can count all submissions, a numeric amount marker, or matching configured tags. Tag matching uses any matching configured condition, not an assumed all-conditions rule.

Date bases vary by widget: activity charts and sales summaries can use `created_at`, while date-range form reports can use the form's `date`. An Agent's form date is forced to the current application day. Check labels and scope instead of assuming all cards use the same period.

## Missing, stale, and partial results

**Unavailable** means a source or metric could not be verified; it does not mean zero. **Stale** means a last successful snapshot is being shown. Partial/degraded results mean some sections may be usable while others are not. Use **Retry** or **Refresh Reports** once, then ask an administrator to check routing and permissions if the issue persists. Do not combine a stale numerator with a fresh denominator manually.

Source: [report interface](../../resources/views/reports/index.blade.php), [historical calculations](../../app/Services/Telephony/HistoricalTelephonyReportService.php), [realtime service](../../app/Services/Telephony/RealtimeTelephonyReportService.php), [dashboard calculations](../../app/Services/DashboardStatsService.php), [sales rules](../../app/Services/DashboardSalesRuleService.php).
